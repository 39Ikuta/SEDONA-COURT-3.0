<?php

namespace App\Http\Controllers;

use App\Models\Folio;
use App\Models\OrderItem;
use App\Models\PosItem;
use App\Services\GuestOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GuestOrderController extends Controller
{
    public function __construct(private GuestOrderService $orders) {}

    public function index(Folio $folio): View
    {
        $folio->load(['room', 'guest', 'orders.items', 'orders.cashier']);
        $folio->setRelation('orders', $folio->orders->sortBy('id')->values());
        $catalog = PosItem::available()->get()->filter(fn (PosItem $item): bool => $item->isOrderable())->groupBy('category');
        $legacyItems = $this->orders->legacyItems($folio);
        $lineIds = $folio->orders->flatMap(fn ($order) => $order->items->pluck('id'));
        $corrections = DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->where('auditable_type', OrderItem::class)->whereIn('auditable_id', $lineIds)
            ->where('event', 'pos.order_corrected')->orderByDesc('audit_logs.id')
            ->select('audit_logs.auditable_id', 'audit_logs.new_values', 'audit_logs.created_at', 'users.name')
            ->get()->unique('auditable_id')->keyBy('auditable_id');

        return view('guest-orders.index', compact('folio', 'catalog', 'legacyItems', 'corrections'));
    }

    public function correct(Request $request, Folio $folio, OrderItem $orderItem): RedirectResponse
    {
        Gate::authorize('review-financial');
        $data = $request->validate([
            'quantity' => 'required|integer|min:0|max:65535',
            'expected_quantity' => 'required|integer|min:0|max:65535',
            'reason' => 'required|string|max:500',
        ]);
        $this->orders->correct($request, $folio, $orderItem, $data);

        return redirect()->route('folios.orders.index', $folio)->with('success', 'Order corrected and the guest bill updated.');
    }
}
