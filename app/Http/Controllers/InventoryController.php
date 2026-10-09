<?php

namespace App\Http\Controllers;

use App\Models\InventoryEvent;
use App\Models\PosItem;
use App\Services\ApprovalService;
use App\Services\FinancialPostingService;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryController extends Controller
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Display the main inventory control & audit screen.
     */
    public function index(Request $request): View
    {
        $query = PosItem::notRetired()->orderBy('category')->orderBy('sort_order');

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->boolean('low_stock')) {
            $query->where('is_tracked', true)->whereColumn('stock_quantity', '<=', 'reorder_level');
        }

        $items = $query->get();

        $categories = PosItem::notRetired()->select('category')->distinct()->pluck('category');

        $lowStockCount = PosItem::notRetired()->where('is_tracked', true)->whereColumn('stock_quantity', '<=', 'reorder_level')->count();
        $totalItems = PosItem::notRetired()->count();
        $totalUnits = PosItem::notRetired()->where('is_tracked', true)->sum('stock_quantity');

        $recentEvents = InventoryEvent::with('posItem')
            ->latest()
            ->take(40)
            ->get();

        return view('inventory.index', compact(
            'items',
            'categories',
            'lowStockCount',
            'totalItems',
            'totalUnits',
            'recentEvents'
        ));
    }

    /**
     * Update individual item stock quantity (Restock / Adjustment).
     */
    public function updateStock(Request $request, PosItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'stock_quantity' => 'required|integer|min:0|max:100000',
            'reason' => 'required|string|in:restock,adjustment,damage_spoilage,return',
            'notes' => 'nullable|string|max:255',
        ]);

        app(FinancialPostingService::class)->execute($request, 'approval.stock', $item->id, $validated, function () use ($request, $item, $validated): array {
            $approval = app(ApprovalService::class)->request($request, 'stock_adjustment', $item->id, $validated, $validated['notes'] ?? $validated['reason']);

            return ['approval_id' => $approval->id];
        });

        return redirect()->route('approvals.index')->with('success', 'Stock change awaits independent review.');
    }

    /**
     * Batch recount all items (Cashier Shift Recount).
     */
    public function batchRecount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'counts' => 'required|array',
            'counts.*' => 'nullable|integer|min:0|max:100000',
            'notes' => 'nullable|string|max:255',
        ]);

        $ids = array_keys(array_filter($validated['counts'], fn ($count): bool => $count !== null));
        if ($ids === []) {
            throw ValidationException::withMessages(['counts' => 'Enter at least one count.']);
        }
        app(FinancialPostingService::class)->execute($request, 'approval.recount', (int) $ids[0], $validated, function () use ($request, $validated, $ids): array {
            $approval = app(ApprovalService::class)->request($request, 'stock_recount', (int) $ids[0], $validated, $validated['notes'] ?? 'Cashier shift recount');

            return ['approval_id' => $approval->id];
        });

        return redirect()->route('approvals.index')->with('success', 'Recount awaits independent review.');
    }

    /**
     * Export Inventory Movement Ledger as CSV.
     */
    public function exportCsv(): Response
    {
        $csv = $this->inventoryService->generateMovementCsv();
        $filename = 'sedona_inventory_ledger_'.now()->format('Ymd_His').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
