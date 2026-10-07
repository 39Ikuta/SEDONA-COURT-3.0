<?php

namespace App\Http\Controllers;

use App\Models\Folio;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Room;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Reports & Executive Business Intelligence Overview.
     */
    public function index(Request $request): View
    {
        $todayRevenue = Folio::whereDate('checked_in_at', today())->sum('net_total')
            + Order::where('type', 'walkin_pos')->whereDate('created_at', today())->sum('total');

        $weekRevenue = Folio::where('checked_in_at', '>=', now()->subDays(7))->sum('net_total')
            + Order::where('type', 'walkin_pos')->where('created_at', '>=', now()->subDays(7))->sum('total');

        $totalDiscountsGiven = Folio::where('checked_in_at', '>=', now()->subDays(7))->sum('discount_amount');
        $totalFoliosThisWeek = Folio::where('checked_in_at', '>=', now()->subDays(7))->count();

        // Revenue Breakdown by Category (Past 7 Days)
        $roomRevenueWeek = Folio::where('checked_in_at', '>=', now()->subDays(7))->sum('room_charge');
        $overtimeRevenueWeek = Folio::where('checked_in_at', '>=', now()->subDays(7))->sum('surcharge_total');
        $posRevenueWeek = Folio::where('checked_in_at', '>=', now()->subDays(7))->sum('pos_total')
            + Order::where('type', 'walkin_pos')->where('created_at', '>=', now()->subDays(7))->sum('total');

        // Baseline Operating Expenses Breakdown (Weekly Standard)
        $standardExpenses = [
            ['category' => 'Kitchen Provisions', 'description' => 'Meat, fish, vegetables, sinangag rice, eggs', 'amount' => 4500.00],
            ['category' => 'Drinking Water Refills', 'description' => 'Wilkins & purified dispenser containers', 'amount' => 650.00],
            ['category' => 'Commercial Laundry', 'description' => 'Ate Lanie Beddings, towels & blanket cleaning', 'amount' => 2800.00],
            ['category' => 'Cleaning & Disinfectants', 'description' => 'Zonrox, tissue flexi-cling, liquid soap', 'amount' => 950.00],
            ['category' => 'Utilities & Cable TV', 'description' => 'George Cable TV, Wi-Fi fiber link allocation', 'amount' => 1800.00],
        ];

        $totalOperatingExpenses = collect($standardExpenses)->sum('amount');
        $grossWeeklyRevenue = $weekRevenue;
        $netWeeklyOperatingProfit = max(0, $grossWeeklyRevenue - $totalOperatingExpenses);

        $recentFolios = Folio::with(['room', 'guest', 'cashier'])
            ->latest('checked_in_at')
            ->paginate(15);

        $pastShifts = Shift::with(['openedBy', 'closedBy'])
            ->latest('created_at')
            ->take(10)
            ->get();

        $rooms = Room::withCount(['folios' => function ($q) {
            $q->where('checked_in_at', '>=', now()->subDays(7));
        }])->orderBy('number')->get();

        return view('reports.index', compact(
            'todayRevenue',
            'weekRevenue',
            'totalDiscountsGiven',
            'totalFoliosThisWeek',
            'roomRevenueWeek',
            'overtimeRevenueWeek',
            'posRevenueWeek',
            'standardExpenses',
            'totalOperatingExpenses',
            'grossWeeklyRevenue',
            'netWeeklyOperatingProfit',
            'recentFolios',
            'pastShifts',
            'rooms'
        ));
    }
}
