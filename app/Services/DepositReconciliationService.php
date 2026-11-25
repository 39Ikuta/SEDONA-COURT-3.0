<?php

namespace App\Services;

use App\Models\Folio;
use App\Models\PaymentEntry;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DepositReconciliationService
{
    /**
     * Keep guest trust balances separate from the scoped cashier's payment movements.
     *
     * @param  Collection<int, PaymentEntry>  $entries
     * @param  Collection<int, Folio>  $legacyFolios
     * @return array<string, mixed>
     */
    public function summarize(Collection $entries, Collection $legacyFolios, CarbonInterface $start, CarbonInterface $end): array
    {
        $end = CarbonImmutable::instance($end)->min(CarbonImmutable::instance(now())->addMicrosecond());
        $settledIds = $entries->whereIn('kind', ['settlement', 'write_off'])->pluck('folio_id')->filter()->merge($legacyFolios->pluck('id'))->unique();
        $ownedRefunds = $entries->where('kind', 'deposit_refund')->pluck('id')->flip();
        $folios = Folio::with(['room', 'paymentEntries' => fn ($query) => $query->whereIn('kind', ['deposit', 'deposit_refund', 'settlement', 'write_off'])->orderBy('id')])
            ->where('security_deposit', '>', 0)
            ->where(function (Builder $query) use ($start, $settledIds): void {
                $query->whereNull('checked_out_at')->orWhere('checked_out_at', '>=', $start)->orWhereIn('id', $settledIds)
                    ->orWhereHas('paymentEntries', fn (Builder $payments): Builder => $payments->whereIn('kind', ['settlement', 'write_off', 'deposit_refund'])->where('posted_at', '>=', $start))
                    ->orWhereRaw('security_deposit > COALESCE(gross_total, 0) - COALESCE(discount_amount, 0) - COALESCE(net_total, 0) + COALESCE(deposit_refunded_amount, 0)');
            })->get();
        $depositIds = $folios->flatMap(fn (Folio $folio) => $folio->paymentEntries->where('kind', 'deposit')->modelKeys());
        $allocations = DB::table('payment_refund_allocations')->join('payment_entries as refunds', 'refunds.id', '=', 'payment_refund_allocations.refund_entry_id')
            ->whereIn('deposit_entry_id', $depositIds)->orderBy('refunds.posted_at')->orderBy('refunds.id')->orderBy('deposit_entry_id')
            ->get(['deposit_entry_id', 'refund_entry_id', 'amount', 'refunds.posted_at'])->groupBy('deposit_entry_id');
        $totals = ['cashReceived' => $entries->where('kind', 'deposit')->sum(fn (PaymentEntry $entry): int => BillingService::cents($entry->cash_amount)),
            'cashRefunded' => -$entries->where('kind', 'deposit_refund')->sum(fn (PaymentEntry $entry): int => BillingService::cents($entry->cash_amount)),
            'cashApplied' => 0, 'cashReleasedByRefund' => 0, 'openingCashHeld' => 0, 'closingCashHeld' => 0, 'closingGcashHeld' => 0,
            'unverifiedHeld' => 0, 'unverifiedApplied' => 0,
            'unverifiedRefunds' => -$entries->where('kind', 'deposit_refund')->sum(fn (PaymentEntry $entry): int => BillingService::cents($entry->cash_amount) + BillingService::cents($entry->gcash_amount))];
        $rooms = [];
        foreach ($folios as $folio) {
            $balances = $this->balances($folio, $allocations, $start, $end);
            if ($settledIds->contains($folio->id)) {
                $totals['cashApplied'] += $balances['appliedCash'];
                $totals['unverifiedApplied'] += $balances['appliedLegacy'];
            }
            foreach ($balances['releasedCash'] as $refundId => $amount) {
                if ($ownedRefunds->has($refundId)) {
                    $totals['cashReleasedByRefund'] += $amount;
                    $totals['unverifiedRefunds'] -= $balances['allocatedByRefund'][$refundId];
                }
            }
            $totals['openingCashHeld'] += $balances['opening']['cash'];
            $totals['closingCashHeld'] += $balances['closing']['cash'];
            $totals['closingGcashHeld'] += $balances['closing']['gcash'];
            $totals['unverifiedHeld'] += $balances['closing']['legacy'];
            if (array_sum($balances['closing']) > 0) {
                $rooms[] = ['room' => $folio->room?->number ?? 'Unknown', 'folio' => $folio->transaction_id,
                    'cash' => $balances['closing']['cash'] / 100, 'gcash' => $balances['closing']['gcash'] / 100, 'unverified' => $balances['closing']['legacy'] / 100];
            }
        }

        return array_map(fn (int $amount): float => $amount / 100, $totals) + ['rooms' => $rooms];
    }

    /**
     * Match posting's legacy-first, then FIFO allocation, preserving the original tender.
     * Historical balances use posted times even after the stay is settled or refunded.
     *
     * @return array<string, mixed>
     */
    private function balances(Folio $folio, Collection $allocations, CarbonInterface $start, CarbonInterface $end): array
    {
        $deposits = $folio->paymentEntries->where('kind', 'deposit');
        $legacy = max(0, BillingService::cents($folio->security_deposit) - $deposits->sum(fn (PaymentEntry $entry): int => BillingService::cents($entry->cash_amount) + BillingService::cents($entry->gcash_amount)));
        $applied = $folio->status === 'checked_out' ? min(BillingService::cents($folio->security_deposit), max(0, BillingService::cents($folio->gross_total) - BillingService::cents($folio->discount_amount) - BillingService::cents($folio->net_total))) : 0;
        $appliedLegacy = min($legacy, $applied);
        $appliedLeft = $applied - $appliedLegacy;
        $applicationAt = $folio->paymentEntries->whereIn('kind', ['settlement', 'write_off'])->first()?->posted_at ?? $folio->checked_out_at;
        $held = ['opening' => ['cash' => 0, 'gcash' => 0, 'legacy' => 0], 'closing' => ['cash' => 0, 'gcash' => 0, 'legacy' => 0]];
        $appliedCash = 0;
        $releasedCash = [];
        $allocatedByRefund = [];
        $allocatedRefunds = ['opening' => 0, 'closing' => 0];
        foreach ($deposits as $deposit) {
            $cash = BillingService::cents($deposit->cash_amount);
            $gcash = BillingService::cents($deposit->gcash_amount);
            $used = min($appliedLeft, $cash + $gcash);
            $usedCash = min($used, $cash);
            $appliedCash += $usedCash;
            $appliedLeft -= $used;
            $cashLeft = $cash - $usedCash;
            $refunds = ['opening' => ['cash' => 0, 'gcash' => 0], 'closing' => ['cash' => 0, 'gcash' => 0]];
            foreach ($allocations->get($deposit->id, collect()) as $allocation) {
                $amount = BillingService::cents($allocation->amount);
                $released = min($cashLeft, $amount);
                $cashLeft -= $released;
                $releasedCash[$allocation->refund_entry_id] = ($releasedCash[$allocation->refund_entry_id] ?? 0) + $released;
                $allocatedByRefund[$allocation->refund_entry_id] = ($allocatedByRefund[$allocation->refund_entry_id] ?? 0) + $amount;
                $at = CarbonImmutable::parse($allocation->posted_at, config('app.timezone'));
                foreach (['opening' => $start, 'closing' => $end] as $key => $boundary) {
                    if ($at->lt($boundary)) {
                        $refunds[$key]['cash'] += $released;
                        $refunds[$key]['gcash'] += $amount - $released;
                        $allocatedRefunds[$key] += $amount;
                    }
                }
            }
            foreach (['opening' => $start, 'closing' => $end] as $key => $boundary) {
                if ($deposit->posted_at->lt($boundary)) {
                    $usedAt = $applicationAt?->lt($boundary) ? $used : 0;
                    $cashUsedAt = $applicationAt?->lt($boundary) ? $usedCash : 0;
                    $held[$key]['cash'] += max(0, $cash - $cashUsedAt - $refunds[$key]['cash']);
                    $held[$key]['gcash'] += max(0, $gcash - ($usedAt - $cashUsedAt) - $refunds[$key]['gcash']);
                }
            }
        }
        foreach (['opening' => $start, 'closing' => $end] as $key => $boundary) {
            if ($folio->checked_in_at->lt($boundary)) {
                $refundTotal = $folio->paymentEntries->where('kind', 'deposit_refund')->filter(fn (PaymentEntry $entry): bool => $entry->posted_at->lt($boundary))
                    ->sum(fn (PaymentEntry $entry): int => -BillingService::cents($entry->cash_amount) - BillingService::cents($entry->gcash_amount));
                if ($folio->deposit_refunded_at?->lt($boundary)) {
                    $refundTotal = max($refundTotal, BillingService::cents($folio->deposit_refunded_amount));
                }
                $held[$key]['legacy'] = max(0, $legacy - ($applicationAt?->lt($boundary) ? $appliedLegacy : 0) - max(0, $refundTotal - $allocatedRefunds[$key]));
            }
        }

        return $held + compact('appliedCash', 'appliedLegacy', 'releasedCash', 'allocatedByRefund');
    }
}
