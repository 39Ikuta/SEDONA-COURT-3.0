<?php

namespace App\Services;

use App\Models\InventoryEvent;
use App\Models\PosItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Atomically decrement stock for a tracked POS item.
     *
     * NEW BEHAVIOUR (Independent Stock Model):
     * - Each dish has its own stock_quantity. Breakfast items (silog) are NO longer pooled.
     * - Dishes that requiresEgg() trigger an ADDITIONAL relational deduction from Egg (Fried/Boiled).
     * - Auto-toggles item availability to false when stock hits zero.
     */
    public function atomicDecrementStock(
        int $posItemId,
        int $qty,
        string $reason = 'sold',
        ?string $referenceId = null,
        ?int $userId = null,
        ?string $notes = null
    ): bool {
        return DB::transaction(function () use ($posItemId, $qty, $reason, $referenceId, $userId, $notes) {
            $item = PosItem::where('id', $posItemId)->lockForUpdate()->first();
            if (!$item || !$item->is_tracked) {
                return true;
            }

            $user = $userId ? \App\Models\User::find($userId) : Auth::user();

            // --- Step 1: Deduct from the dish's OWN stock ---
            $currentStock = (int) ($item->getRawOriginal('stock_quantity') ?? 0);
            $newStock = max(0, $currentStock - $qty);

            $item->stock_quantity = $newStock;
            $item->is_available = $newStock > 0;
            $item->save();

            InventoryEvent::create([
                'pos_item_id'     => $item->id,
                'item_name'       => $item->name,
                'event_type'      => $reason,
                'quantity_change' => -$qty,
                'balance_after'   => $newStock,
                'reference_id'    => $referenceId,
                'user_id'         => $user?->id,
                'operator_name'   => $user?->name ?? 'System Cashier',
                'notes'           => $notes ?? "Stock decreased by {$qty} via {$reason}",
            ]);

            // --- Step 2: If the dish uses eggs, also deduct from Egg pantry relationally ---
            if ($item->requiresEgg()) {
                $eggQty = $item->getEggRequirementQty() * $qty;
                $this->deductRelationalEggStock($item, $eggQty, $referenceId, $user?->id, $notes);
            }

            return true;
        });
    }

    /**
     * Deduct egg stock relationally for composite/egg-dependent dishes.
     * Logs a "recipe_ingredient" event on the egg item.
     */
    public function deductRelationalEggStock(
        PosItem $dishItem,
        int $eggQty,
        ?string $referenceId = null,
        ?int $userId = null,
        ?string $notes = null
    ): ?PosItem {
        $eggItem = PosItem::where('name', 'Egg (Fried/Boiled)')
            ->orWhere('name', 'like', '%Egg (Fried/Boiled)%')
            ->orWhere(function ($q) {
                $q->where('name', 'like', '%Egg%')->where('category', 'Kitchen Extras');
            })
            ->lockForUpdate()
            ->first();

        if (!$eggItem || $eggItem->id === $dishItem->id || !$eggItem->is_tracked) {
            return null;
        }

        $currentStock = (int) ($eggItem->getRawOriginal('stock_quantity') ?? 0);
        $newStock = max(0, $currentStock - $eggQty);

        $eggItem->stock_quantity = $newStock;
        $eggItem->is_available = $newStock > 0;
        $eggItem->save();

        $user = $userId ? \App\Models\User::find($userId) : Auth::user();

        InventoryEvent::create([
            'pos_item_id'     => $eggItem->id,
            'item_name'       => $eggItem->name,
            'event_type'      => 'recipe_ingredient',
            'quantity_change' => -$eggQty,
            'balance_after'   => $newStock,
            'reference_id'    => $referenceId,
            'user_id'         => $user?->id,
            'operator_name'   => $user?->name ?? 'Kitchen Recipe System',
            'notes'           => "Recipe ingredient: {$eggQty}x egg used for order of {$dishItem->name}" . ($notes ? " ({$notes})" : ""),
        ]);

        PosItem::clearEggCache();

        return $eggItem;
    }

    /**
     * Set exact stock count for a single item (manual adjustment or restock).
     * Each item manages its own stock independently.
     */
    public function setStockCount(
        int $posItemId,
        int $newCount,
        string $reason = 'adjustment',
        ?string $notes = null,
        ?int $userId = null
    ): PosItem {
        return DB::transaction(function () use ($posItemId, $newCount, $reason, $notes, $userId) {
            $item = PosItem::where('id', $posItemId)->lockForUpdate()->firstOrFail();
            $user = $userId ? \App\Models\User::find($userId) : Auth::user();

            $prevStock = (int) ($item->getRawOriginal('stock_quantity') ?? 0);
            $diff = $newCount - $prevStock;

            $item->stock_quantity = max(0, $newCount);
            $item->is_available = $newCount > 0;
            $item->save();

            InventoryEvent::create([
                'pos_item_id'     => $item->id,
                'item_name'       => $item->name,
                'event_type'      => $reason,
                'quantity_change' => $diff,
                'balance_after'   => $item->stock_quantity,
                'user_id'         => $user?->id,
                'operator_name'   => $user?->name ?? 'Duty Operator',
                'notes'           => $notes ?? "Stock set from {$prevStock} to {$newCount} ({$reason})",
            ]);

            PosItem::clearEggCache();

            return $item;
        });
    }

    /**
     * Batch recount all items (Cashier Shift Inventory Recount).
     * Each item is independently updated — no pooling sync required.
     */
    public function batchRecount(array $counts, ?int $userId = null, ?string $notes = null): int
    {
        $updatedCount = 0;
        foreach ($counts as $itemId => $count) {
            if ($count === null || $count === '') {
                continue;
            }
            $this->setStockCount((int) $itemId, (int) $count, 'recount', $notes, $userId);
            $updatedCount++;
        }

        return $updatedCount;
    }

    /**
     * Export Inventory Movement Ledger as CSV.
     */
    public function generateMovementCsv(): string
    {
        $events = InventoryEvent::with('posItem')->latest()->get();
        $output = fopen('php://temp', 'r+');

        fputcsv($output, [
            'ID', 'Date Time', 'Item Name', 'Category', 'Event Type',
            'Quantity Change', 'Balance After', 'Reference ID', 'Operator', 'Notes'
        ]);

        foreach ($events as $e) {
            fputcsv($output, [
                $e->id,
                $e->created_at->format('Y-m-d H:i:s'),
                $e->item_name,
                $e->posItem->category ?? 'N/A',
                strtoupper($e->event_type),
                $e->quantity_change > 0 ? "+{$e->quantity_change}" : $e->quantity_change,
                $e->balance_after,
                $e->reference_id ?? '—',
                $e->operator_name ?? 'Staff',
                $e->notes ?? '',
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Legacy helper kept for backwards compat — no longer pools breakfast stock.
     * Now only clears the egg cache to ensure fresh reads.
     *
     * @deprecated Use deductRelationalEggStock() directly.
     */
    public function syncBreakfastItemsStockWithEgg(?PosItem $eggItem = null): void
    {
        PosItem::clearEggCache();
    }
}