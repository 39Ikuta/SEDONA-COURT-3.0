<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Order;
use App\Models\PaymentEntry;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ShiftAccessService
{
    public function canOperate(User $actor, Shift $shift): bool
    {
        return $actor->can('manage_shifts') && ((int) $actor->id === (int) $shift->opened_by
            || ($actor->hasAnyRole(['admin', 'owner']) && $actor->can('manage_any_shifts')));
    }

    public function visibleShifts(User $actor): Builder
    {
        $query = Shift::query();
        if ($actor->hasAnyRole(['admin', 'owner']) && $actor->can('view_all_shifts')) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($actor): void {
            $query->where('opened_by', $actor->id)->orWhere('closed_by', $actor->id)
                ->orWhereIn('id', PaymentEntry::where('user_id', $actor->id)->whereNotNull('shift_id')->select('shift_id'))
                ->orWhereIn('id', Order::where('user_id', $actor->id)->whereNotNull('shift_id')->select('shift_id'))
                ->orWhereIn('id', Expense::where('user_id', $actor->id)->whereNotNull('shift_id')->select('shift_id'));
        });
    }

    public function requireOperatingShift(User $actor, ?string $overrideReason = null, string $action = 'payment'): Shift
    {
        Gate::forUser($actor)->authorize('record_payments');
        $shift = Shift::whereNull('closed_at')->lockForUpdate()->first();
        if (! $shift || $shift->is_frozen) {
            throw ValidationException::withMessages(['shift' => 'Open an unfrozen shift before recording money movements.']);
        }
        $this->authorizeOperation($actor, $shift, $overrideReason, $action);

        return $shift;
    }

    public function authorizeOperation(User $actor, Shift $shift, ?string $overrideReason, string $action): void
    {
        Gate::forUser($actor)->authorize('operate-shift', $shift);
        if ((int) $actor->id === (int) $shift->opened_by) {
            return;
        }
        $reason = trim($overrideReason ?? '');
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['shift_override_reason' => 'Enter a reason of up to 500 characters to operate another cashier\'s drawer.']);
        }
        DB::table('audit_logs')->insert([
            'user_id' => $actor->id, 'event' => 'shift.override',
            'auditable_type' => Shift::class, 'auditable_id' => $shift->id,
            'old_values' => json_encode(['opened_by' => $shift->opened_by], JSON_THROW_ON_ERROR),
            'new_values' => json_encode(['action' => $action, 'reason' => $reason], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
