<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Folio;
use App\Models\Order;
use App\Models\PosItem;
use App\Models\Room;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        if (auth()->check()) {
            if (auth()->user()->hasRole('kitchen')) {
                return redirect()->route('kitchen.view');
            }
            if (auth()->user()->hasRole('customer_display')) {
                return redirect()->route('kiosk.view');
            }
        }

        $selectedFloor = $request->query('floor', 'all');
        $selectedStatus = $request->query('status', 'all');

        $query = Room::with(['activeFolio.guest', 'activeFolio.orders.items']);

        if ($selectedFloor !== 'all') {
            $query->where('floor', (int) $selectedFloor);
        }

        if ($selectedStatus !== 'all') {
            $query->where('status', $selectedStatus);
        }

        // Ensure Staff Quarters (Room 12) has an initialized staff folio for F&B add-ons
        $staffRoom = Room::where('is_staff_quarters', true)->first();
        if ($staffRoom && !$staffRoom->activeFolio) {
            $staffRoom->getOrCreateStaffFolio();
        }

        $rooms = $query->orderBy('number')->get();

        // Overall stats (3-state room lifecycle: available, occupied, maintenance)
        $allRooms = Room::all();
        $totalRooms = $allRooms->where('is_staff_quarters', false)->count();
        $availableRooms = $allRooms->where('status', 'available')->where('is_staff_quarters', false)->count();
        $occupiedRooms = $allRooms->where('status', 'occupied')->count();
        $maintenanceRooms = $allRooms->where('status', 'maintenance')->count();
        $occupancyRate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 1) : 0;

        // Active shift
        $activeShift = Shift::whereNull('closed_at')->latest()->first();

        // Available rooms for fast transfer modal
        $availableRoomsList = Room::where('status', 'available')
            ->where('is_staff_quarters', false)
            ->orderBy('number')
            ->get();

        // Pending force checkout requests
        $pendingForceCheckouts = Folio::with(['room', 'guest', 'cashier'])
            ->where('force_checkout', true)
            ->where('status', 'active')
            ->get();

        // Active folios
        $activeFolios = Folio::with(['room', 'guest', 'orders'])
            ->where('status', 'active')
            ->orderBy('expected_checkout_at')
            ->get();

        // Upcoming bookings for today/tomorrow
        $upcomingBookings = Booking::with('room')
            ->where('status', 'scheduled')
            ->where('arrival_at', '>=', now()->startOfDay())
            ->where('arrival_at', '<=', now()->addDays(2)->endOfDay())
            ->orderBy('arrival_at')
            ->take(6)
            ->get();

        // Pos Items for quick order modal
        $posItems = PosItem::where('is_available', true)->orderBy('category')->orderBy('name')->get();

        // ── Executive apartment board (owner/admin/manager) ──────────
        // Full room collection with folio relations; counts always computed
        // from the unfiltered set so sidebar numbers stay truthful.
        $boardAll = Room::with(['activeFolio.guest'])->orderBy('number')->get();
        $boardTotal = $boardAll->count();
        $boardOcc = $boardAll->where('status', 'occupied')->count();
        $boardAvail = $boardAll->where('status', 'available')->where('is_staff_quarters', false)->count();
        $boardLate = 0;
        $boardAlmost = 0;
        foreach ($boardAll as $br) {
            $bf = $br->activeFolio;
            if ($br->status === 'occupied' && $bf && $bf->expected_checkout_at) {
                if (now()->gt($bf->expected_checkout_at)) {
                    $boardLate++;
                } elseif (now()->diffInMinutes($bf->expected_checkout_at, false) <= 30) {
                    $boardAlmost++;
                }
            }
        }
        $boardRate = $boardTotal > 0 ? (int) round($boardOcc / $boardTotal * 100) : 0;
        $boardCounts = [
            'all' => $boardTotal,
            'available' => $boardAvail,
            'occupied' => $boardOcc,
            'late' => $boardLate,
            'almost' => $boardAlmost,
            'maintenance' => $boardAll->where('status', 'maintenance')->count(),
        ];

        // Board filters (collection-level; tables view ignores these params).
        $bstatus = $request->query('bstatus', 'all');
        $btier = $request->query('btier', 'all');
        $boardRooms = $boardAll;
        if (in_array($bstatus, ['available', 'occupied', 'maintenance'], true)) {
            $boardRooms = $boardRooms->where('status', $bstatus);
            if ($bstatus === 'available') {
                $boardRooms = $boardRooms->where('is_staff_quarters', false);
            }
        } elseif ($bstatus === 'late') {
            $boardRooms = $boardRooms->filter(fn ($r) => $r->status === 'occupied' && $r->activeFolio && $r->activeFolio->expected_checkout_at && now()->gt($r->activeFolio->expected_checkout_at));
        } elseif ($bstatus === 'almost') {
            $boardRooms = $boardRooms->filter(fn ($r) => $r->status === 'occupied' && $r->activeFolio && $r->activeFolio->expected_checkout_at && now()->lte($r->activeFolio->expected_checkout_at) && now()->diffInMinutes($r->activeFolio->expected_checkout_at, false) <= 30);
        }
        if (in_array($btier, ['vip', 'premium', 'classic'], true)) {
            $boardRooms = $boardRooms->filter(fn ($r) => $this->roomTierKey($r) === $btier);
        }

        // Notification items for the executive tab bar dropdown.
        $lowStockCount = PosItem::where('is_tracked', true)->whereColumn('stock_quantity', '<=', 'reorder_level')->count();
        $notifItems = array_values(array_filter([
            count($pendingForceCheckouts) > 0 ? ['count' => count($pendingForceCheckouts), 'label' => 'pending force checkout approval(s)', 'url' => route('dashboard')] : null,
            $boardLate > 0 ? ['count' => $boardLate, 'label' => 'late checkout(s) on the board', 'url' => route('dashboard', ['bstatus' => 'late'])] : null,
            $lowStockCount > 0 ? ['count' => $lowStockCount, 'label' => 'item(s) at or below reorder level', 'url' => route('inventory.index')] : null,
        ]));
        $notifCount = array_sum(array_column($notifItems, 'count'));

        // Personal shift performance (cashier scope: own folios/orders within active shift window).
        // Admins keep full BI in Reports; cashiers must never see global sales.
        $myShiftRevenue = 0.0;
        $myCheckIns = 0;
        $myOrdersCount = 0;
        $myShiftLabel = 'No open shift';

        if (auth()->check() && auth()->user()->hasAnyRole(['cashier', 'front_desk'])) {
            $myId = auth()->id();
            $myShiftStart = $activeShift?->opened_at ?? today()->startOfDay();
            $myShiftLabel = $activeShift
                ? ucfirst($activeShift->shift_type ?? 'shift') . ' · opened ' . $activeShift->opened_at->format('m/d h:i A')
                : 'Today';

            $myCheckIns = Folio::where('user_id', $myId)
                ->where('checked_in_at', '>=', $myShiftStart)
                ->count();

            $myFolioRevenue = (float) Folio::where('user_id', $myId)
                ->where('checked_in_at', '>=', $myShiftStart)
                ->sum('net_total');

            $myPosRevenue = (float) Order::where('user_id', $myId)
                ->where('type', 'walkin_pos')
                ->where('created_at', '>=', $myShiftStart)
                ->sum('total');

            $myOrdersCount = Order::where('user_id', $myId)
                ->where('created_at', '>=', $myShiftStart)
                ->count();

            $myShiftRevenue = $myFolioRevenue + $myPosRevenue;
        }

        // Owner/admin/manager get the apartment board; cashiers keep the tables.
        $boardView = auth()->check() && auth()->user()->hasAnyRole(['owner', 'admin', 'manager']);

        return view($boardView ? 'dashboard.board' : 'dashboard.index', compact(
            'rooms',
            'totalRooms',
            'availableRooms',
            'occupiedRooms',
            'maintenanceRooms',
            'occupancyRate',
            'activeShift',
            'activeFolios',
            'upcomingBookings',
            'posItems',
            'availableRoomsList',
            'pendingForceCheckouts',
            'selectedFloor',
            'selectedStatus',
            'myShiftRevenue',
            'myCheckIns',
            'myOrdersCount',
            'myShiftLabel',
            'boardRooms',
            'boardTotal',
            'boardCounts',
            'boardRate',
            'bstatus',
            'btier',
            'notifCount',
            'notifItems'
        ));
    }

    /**
     * Canonical room tier key derived from the room type label.
     */
    private function roomTierKey(Room $room): string
    {
        $upper = strtoupper($room->type ?? '');

        if (str_contains($upper, 'VIP') || str_contains($upper, 'SUITE')) {
            return 'vip';
        }

        if (str_contains($upper, 'PREMIUM')) {
            return 'premium';
        }

        return 'classic';
    }

    /**
     * Dedicated Lobby Customer Display Kiosk (Read-only, sanitized, 32-room 8x4 matrix).
     */
    public function kioskView(): View
    {
        $rooms = Room::orderBy('number')->get();
        $totalRooms = $rooms->count();
        $availableRooms = $rooms->where('status', 'available')->count();
        $occupiedRooms = $rooms->where('status', 'occupied')->count();
        $maintenanceRooms = $rooms->where('status', 'maintenance')->count();

        return view('kiosk.index', compact(
            'rooms',
            'totalRooms',
            'availableRooms',
            'occupiedRooms',
            'maintenanceRooms'
        ));
    }
}

