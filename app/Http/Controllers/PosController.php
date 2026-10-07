<?php

namespace App\Http\Controllers;

use App\Models\Folio;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosItem;
use App\Models\Room;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(Request $request): View
    {
        $selectedCategory = $request->query('category', 'all');

        $categories = PosItem::select('category')->distinct()->pluck('category');

        $query = PosItem::where('is_available', true)->orderBy('sort_order');
        if ($selectedCategory !== 'all') {
            $query->where('category', $selectedCategory);
        }
        $posItems = $query->get();

        $occupiedRooms = Room::with('activeFolio.guest')
            ->where(function ($q) {
                $q->where('status', 'occupied')
                  ->orWhere('is_staff_quarters', true);
            })
            ->orderBy('number')
            ->get();

        foreach ($occupiedRooms as $occ) {
            if ($occ->is_staff_quarters && !$occ->activeFolio) {
                $occ->getOrCreateStaffFolio();
                $occ->load('activeFolio.guest');
            }
        }

        $recentOrders = Order::with(['room', 'folio.guest', 'items'])
            ->latest()
            ->take(15)
            ->get();

        return view('pos.index', compact('posItems', 'categories', 'selectedCategory', 'occupiedRooms', 'recentOrders'));
    }

    /**
     * Add Quick Add-On to Room Folio (from Dashboard Add On button).
     */
    public function addQuickAddOn(Request $request, Folio $folio): RedirectResponse
    {
        $validated = $request->validate([
            'pos_item_id' => 'required|exists:pos_items,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $posItem = PosItem::findOrFail($validated['pos_item_id']);
        $qty = (int) $validated['quantity'];

        if ($posItem->is_tracked && $posItem->stock_quantity < $qty) {
            return back()->with('error', "Insufficient stock for {$posItem->name} (Only {$posItem->stock_quantity} available).");
        }

        if ($posItem->requiresEgg()) {
            $egg = PosItem::getMasterEggItem();
            $eggStock = $egg ? (int) $egg->stock_quantity : 0;
            $requiredEggs = $posItem->getEggRequirementQty() * $qty;
            if ($egg && $egg->is_tracked && $eggStock < $requiredEggs) {
                return back()->with('error', "Insufficient egg stock in pantry (Dish requires {$requiredEggs} eggs, but only {$eggStock} available).");
            }
        }

        $subtotal = $posItem->price * $qty;

        $items = $folio->pos_items ?? [];
        $items[] = [
            'name' => $posItem->name,
            'qty' => $qty,
            'price' => (float) $posItem->price,
            'subtotal' => (float) $subtotal,
        ];

        $folio->pos_items = $items;
        $folio->recalculate();

        // Create Order record for tracking
        $order = Order::create([
            'user_id' => Auth::id() ?? 1,
            'folio_id' => $folio->id,
            'room_id' => $folio->room_id,
            'transaction_id' => 'ORD-' . strtoupper(uniqid()),
            'type' => 'room_charge',
            'status' => 'delivered',
            'payment_method' => 'charge_to_room',
            'total' => $subtotal,
            'dispatched_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'pos_item_id' => $posItem->id,
            'item_name' => $posItem->name,
            'unit_price' => $posItem->price,
            'quantity' => $qty,
            'subtotal' => $subtotal,
        ]);

        app(\App\Services\InventoryService::class)->atomicDecrementStock(
            $posItem->id,
            $qty,
            'quick_addon',
            'FOLIO-' . $folio->id,
            Auth::id(),
            "Room {$folio->room->number} Folio Add-On"
        );

        return redirect()->route('dashboard')
            ->with('success', "Added {$qty}x {$posItem->name} to Room {$folio->room->number} Folio.");
    }

    /**
     * Show Printable Order Slip.
     */
    public function showOrderSlip(Folio $folio): View
    {
        $folio->load(['room', 'guest', 'orders.items']);

        return view('pos.orderslip', compact('folio'));
    }

    public function storeOrder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_type' => 'required|in:room_charge,walkin_pos',
            'room_id' => 'required_if:order_type,room_charge|nullable|exists:rooms,id',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:pos_items,id',
            'items.*.qty' => 'required|integer|min:1',
            'payment_method' => 'nullable|in:cash,gcash',
        ]);

        DB::beginTransaction();
        try {
            $folio = null;
            $room = null;

            if ($validated['order_type'] === 'room_charge') {
                $room = Room::findOrFail($validated['room_id']);
                $folio = $room->is_staff_quarters ? $room->getOrCreateStaffFolio() : $room->activeFolio;
            }

            // Pre-validation pass: Check individual item stocks and aggregate egg requirements
            $eggItem = PosItem::getMasterEggItem();
            $availableEggs = ($eggItem && $eggItem->is_tracked) ? (int) $eggItem->stock_quantity : PHP_INT_MAX;
            $requiredEggs = 0;

            foreach ($validated['items'] as $itemData) {
                if (($itemData['qty'] ?? 0) <= 0) continue;
                $checkItem = PosItem::findOrFail($itemData['id']);
                $checkQty = (int) $itemData['qty'];

                if ($checkItem->is_tracked && $checkItem->stock_quantity < $checkQty) {
                    throw new \Exception("Insufficient stock for {$checkItem->name} (Only {$checkItem->stock_quantity} available, ordered {$checkQty}).");
                }

                if ($checkItem->isEggItem()) {
                    $requiredEggs += $checkQty;
                } elseif ($checkItem->requiresEgg()) {
                    $requiredEggs += $checkItem->getEggRequirementQty() * $checkQty;
                }
            }

            if ($eggItem && $eggItem->is_tracked && $requiredEggs > $availableEggs) {
                throw new \Exception("Insufficient egg stock in kitchen pantry (Order requires {$requiredEggs} eggs, but only {$availableEggs} available).");
            }

            $orderNumber = 'ORD-' . strtoupper(uniqid());

            $order = Order::create([
                'user_id' => Auth::id() ?? 1,
                'folio_id' => $folio?->id,
                'room_id' => $room?->id,
                'transaction_id' => $orderNumber,
                'type' => $validated['order_type'],
                'status' => 'new',
                'payment_method' => $validated['order_type'] === 'room_charge' ? 'charge_to_room' : ($validated['payment_method'] ?? 'cash'),
                'total' => 0,
            ]);

            $totalOrderAmount = 0;
            $folioItemsPayload = $folio ? ($folio->pos_items ?? []) : [];
            $inventoryService = app(\App\Services\InventoryService::class);

            foreach ($validated['items'] as $itemData) {
                if (($itemData['qty'] ?? 0) <= 0) continue;

                $posItem = PosItem::findOrFail($itemData['id']);
                $qty = (int) $itemData['qty'];
                $subtotal = $posItem->price * $qty;
                $totalOrderAmount += $subtotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'pos_item_id' => $posItem->id,
                    'item_name' => $posItem->name,
                    'unit_price' => $posItem->price,
                    'quantity' => $qty,
                    'subtotal' => $subtotal,
                ]);

                $inventoryService->atomicDecrementStock(
                    $posItem->id,
                    $qty,
                    $validated['order_type'] === 'room_charge' ? 'room_charge' : 'walkin_pos',
                    $orderNumber,
                    Auth::id(),
                    $folio ? "Charged to Room {$room->number}" : "Walk-in POS Order"
                );

                if ($folio) {
                    $folioItemsPayload[] = [
                        'name' => $posItem->name,
                        'qty' => $qty,
                        'price' => (float) $posItem->price,
                        'subtotal' => (float) $subtotal,
                    ];
                }
            }

            $order->total = $totalOrderAmount;
            $order->save();

            if ($folio) {
                $folio->pos_items = $folioItemsPayload;
                $folio->recalculate();
            }

            DB::commit();

            return redirect()->route('pos.index')
                ->with('success', "Order placed successfully! (Total: ₱" . number_format($totalOrderAmount, 2) . ")");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Order failed: ' . $e->getMessage());
        }
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:new,preparing,ready,delivered',
        ]);

        $order->status = $validated['status'];
        if ($validated['status'] === 'delivered') {
            $order->dispatched_at = now();
        }
        $order->save();

        return back()->with('success', "Order status updated to " . strtoupper($order->status) . ".");
    }

    /**
     * Dedicated Kitchen Display System (KDS) for Kitchen Staff.
     */
    public function kitchenView(): View
    {
        $pendingOrders = Order::with(['room', 'folio.guest', 'items'])
            ->whereIn('status', ['new', 'preparing', 'ready'])
            ->orderBy('created_at', 'asc')
            ->get();

        $completedToday = Order::with(['room', 'folio.guest', 'items'])
            ->where('status', 'delivered')
            ->whereDate('updated_at', today())
            ->latest('updated_at')
            ->take(10)
            ->get();

        return view('kitchen.index', compact('pendingOrders', 'completedToday'));
    }

    /**
     * Dedicated Kitchen TV Display (Optimized for 40-65" TV Monitors).
     */
    public function kitchenTvView(): View
    {
        $pendingOrders = Order::with(['room', 'folio.guest', 'items'])
            ->whereIn('status', ['new', 'preparing', 'ready'])
            ->orderBy('created_at', 'asc')
            ->get();

        $groupedByRoom = $pendingOrders->groupBy(function ($order) {
            return $order->room ? 'Room ' . $order->room->number : 'Walk-In';
        });

        $totalRoomsInQueue = $groupedByRoom->count();
        $totalItemsPending = $pendingOrders->sum(fn($o) => $o->items->sum('quantity'));
        $newCount = $pendingOrders->where('status', 'new')->count();
        $prepCount = $pendingOrders->where('status', 'preparing')->count();
        $readyCount = $pendingOrders->where('status', 'ready')->count();

        return view('kitchen.tv', compact('pendingOrders', 'groupedByRoom', 'totalRoomsInQueue', 'totalItemsPending', 'newCount', 'prepCount', 'readyCount'));
    }
}

