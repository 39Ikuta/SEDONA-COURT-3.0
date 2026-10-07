<?php

namespace App\Http\Controllers;

use App\Models\InventoryEvent;
use App\Models\PosItem;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
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
        $query = PosItem::query()->orderBy('category')->orderBy('sort_order');

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->boolean('low_stock')) {
            $query->whereColumn('stock_quantity', '<=', 'reorder_level');
        }

        $items = $query->get();

        $categories = PosItem::select('category')->distinct()->pluck('category');

        $lowStockCount = PosItem::whereColumn('stock_quantity', '<=', 'reorder_level')->count();
        $totalItems = PosItem::count();
        $totalUnits = PosItem::where('is_tracked', true)->sum('stock_quantity');

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
            'reason'         => 'required|string|in:restock,adjustment,damage_spoilage,return',
            'notes'          => 'nullable|string|max:255',
        ]);

        $this->inventoryService->setStockCount(
            $item->id,
            (int) $validated['stock_quantity'],
            $validated['reason'],
            $validated['notes'],
            Auth::id()
        );

        return back()->with('success', "Stock for {$item->name} updated to {$validated['stock_quantity']} units.");
    }

    /**
     * Batch recount all items (Cashier Shift Recount).
     */
    public function batchRecount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'counts' => 'required|array',
            'counts.*' => 'nullable|integer|min:0|max:100000',
            'notes'  => 'nullable|string|max:255',
        ]);

        $updatedCount = $this->inventoryService->batchRecount(
            $validated['counts'],
            Auth::id(),
            $validated['notes'] ?? 'Cashier Shift Recount'
        );

        return back()->with('success', "Batch recount completed: {$updatedCount} items audited and updated.");
    }

    /**
     * Export Inventory Movement Ledger as CSV.
     */
    public function exportCsv(): Response
    {
        $csv = $this->inventoryService->generateMovementCsv();
        $filename = 'sedona_inventory_ledger_' . now()->format('Ymd_His') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
