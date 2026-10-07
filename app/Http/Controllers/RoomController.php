<?php

namespace App\Http\Controllers;

use App\Models\PosItem;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RoomController extends Controller
{
    /**
     * Update Room Status (e.g. available <-> maintenance).
     */
    public function updateStatus(Request $request, Room $room): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:available,occupied,maintenance',
            'notes' => 'nullable|string|max:500',
        ]);

        $oldStatus = $room->status;
        $room->status = $validated['status'];
        if ($request->filled('notes')) {
            $room->notes = $validated['notes'];
        }
        $room->save();

        $message = "Room {$room->number} status changed from " . strtoupper($oldStatus) . " to " . strtoupper($room->status) . ".";

        return back()->with('success', $message);
    }

    /**
     * Toggle Maintenance Out-of-Order Mode.
     */
    public function toggleMaintenance(Room $room): RedirectResponse
    {
        if ($room->status === 'occupied') {
            return back()->with('error', "Cannot set Room {$room->number} to maintenance while occupied.");
        }

        $newStatus = ($room->status === 'maintenance') ? 'available' : 'maintenance';
        $room->status = $newStatus;
        $room->save();

        return back()->with('success', "Room {$room->number} is now marked as " . strtoupper($newStatus) . ".");
    }

    /**
     * Master Pricing & Catalog Maintenance View (Admin / Owner).
     */
    public function pricingIndex(): View
    {
        abort_unless(auth()->user()->hasAnyRole(['admin', 'owner']), 403, 'Unauthorized. Master Pricing Editor is restricted to Administrators and Owners only.');

        $rooms = Room::orderBy('floor')->orderBy('number')->get();
        $posItems = PosItem::orderBy('category')->orderBy('sort_order')->get();

        return view('admin.pricing', compact('rooms', 'posItems'));
    }

    /**
     * Update Room Tier Rate or POS Item Price (Admin / Owner).
     */
    public function updatePricing(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['admin', 'owner']), 403, 'Unauthorized. Master Pricing Editor is restricted to Administrators and Owners only.');

        if ($request->has('room_id')) {
            $validated = $request->validate([
                'room_id' => 'required|exists:rooms,id',
                'base_rate_3h' => 'required|numeric|min:0',
                'base_rate_6h' => 'required|numeric|min:0',
                'base_rate_12h' => 'required|numeric|min:0',
                'base_rate_24h' => 'required|numeric|min:0',
                'base_rate_promo' => 'required|numeric|min:0',
            ]);

            $room = Room::findOrFail($validated['room_id']);
            $room->update($validated);

            return back()->with('success', "Rates updated for Room {$room->number}.");
        }

        if ($request->has('pos_item_id')) {
            $validated = $request->validate([
                'pos_item_id' => 'required|exists:pos_items,id',
                'price' => 'required|numeric|min:0',
                'is_available' => 'nullable|boolean',
            ]);

            $item = PosItem::findOrFail($validated['pos_item_id']);
            $item->price = $validated['price'];
            $item->is_available = $request->boolean('is_available', true);
            $item->save();

            return back()->with('success', "Price updated for {$item->name}.");
        }

        return back()->with('error', 'No price update data received.');
    }

    /**
     * Store a new POS Catalog Item (Admin / Owner).
     */
    public function storeItem(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['admin', 'owner']), 403, 'Unauthorized.');

        $validated = $request->validate([
            'name'              => 'required|string|max:120|unique:pos_items,name',
            'category'          => 'required|string|max:60',
            'price'             => 'required|numeric|min:0',
            'is_tracked'        => 'nullable|boolean',
            'stock_quantity'    => 'nullable|integer|min:0',
            'reorder_level'     => 'nullable|integer|min:0',
            'kitchen_hours_only'=> 'nullable|boolean',
            'is_available'      => 'nullable|boolean',
        ]);

        $maxSort = PosItem::where('category', $validated['category'])->max('sort_order') ?? 0;

        PosItem::create([
            'name'               => trim($validated['name']),
            'category'           => trim($validated['category']),
            'price'              => $validated['price'],
            'is_tracked'         => $request->boolean('is_tracked', false),
            'stock_quantity'     => $request->boolean('is_tracked') ? (int)($validated['stock_quantity'] ?? 0) : 0,
            'reorder_level'      => (int)($validated['reorder_level'] ?? 5),
            'kitchen_hours_only' => $request->boolean('kitchen_hours_only', false),
            'is_available'       => $request->boolean('is_available', true),
            'sort_order'         => $maxSort + 1,
        ]);

        return back()->with('success', "New catalog item \u201c{$validated['name']}\u201d added successfully.");
    }

    /**
     * Delete a POS Catalog Item (Admin / Owner).
     */
    public function destroyItem(PosItem $posItem): RedirectResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['admin', 'owner']), 403, 'Unauthorized.');

        $name = $posItem->name;
        $posItem->delete();

        return back()->with('success', "Catalog item \u201c{$name}\u201d has been removed.");
    }
}
