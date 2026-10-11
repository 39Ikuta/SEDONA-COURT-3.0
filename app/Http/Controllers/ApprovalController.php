<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Folio;
use App\Models\PaymentEntry;
use App\Models\PosItem;
use App\Models\Shift;
use App\Models\StatementLine;
use App\Services\ApprovalService;
use App\Services\FinancialPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function __construct(private ApprovalService $approvals, private FinancialPostingService $posting) {}

    public function index(Request $request): View
    {
        $query = ApprovalRequest::query();
        if (! $request->user()->can('review-financial')) {
            $query->where('requested_by', $request->user()->id);
        }

        $folios = Folio::with(['guest', 'room'])->latest()->limit(100)->get();
        $selectedFolio = $folios->firstWhere('id', $request->integer('folio_id'));
        if ($selectedFolio && (! $selectedFolio->isActive() || $selectedFolio->room->is_staff_quarters)) {
            $selectedFolio = null;
        }
        $discountType = $request->query('discount_type');
        $selectedDiscountType = is_string($discountType) && in_array(strtoupper($discountType), ['DC', 'SENIOR', 'PWD', 'NONE'], true)
            ? strtoupper($discountType) : null;

        return view('accounting.approvals', [
            'requests' => $query->latest()->paginate(20),
            'folios' => $folios,
            'selectedFolio' => $selectedFolio,
            'selectedDiscountType' => $selectedFolio ? $selectedDiscountType : null,
            'payments' => PaymentEntry::where('kind', 'pos_sale')->when(! $request->user()->can('review-financial'), fn ($q) => $q->where('user_id', $request->user()->id))->latest()->limit(100)->get(),
            'statements' => $request->user()->can('review-financial') ? StatementLine::latest()->limit(100)->get() : collect(),
            'items' => PosItem::notRetired()->orderBy('name')->get(),
            'shift' => Shift::whereNull('closed_at')->first(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['kind' => 'required|string', 'source_id' => 'required|integer|min:1', 'payload' => 'nullable|array', 'reason' => 'required|string|max:1000', 'evidence_reference' => 'nullable|string|max:255']);
        $result = $this->posting->execute($request, 'approval.request', $data['source_id'], $data, function () use ($request, $data): array {
            $approval = $this->approvals->request($request, $data['kind'], $data['source_id'], array_filter($data['payload'] ?? [], fn ($value): bool => $value !== null), $data['reason'], $data['evidence_reference'] ?? null);

            return ['approval_id' => $approval->id];
        });

        return redirect()->route('approvals.index')->with('success', 'Request #'.$result['approval_id'].' awaits independent review.');
    }

    public function review(Request $request, ApprovalRequest $approval): RedirectResponse
    {
        $data = $request->validate(['decision' => 'required|in:approve,reject', 'note' => 'required|string|max:1000']);
        $this->approvals->review($request, $approval, $data['decision'] === 'approve', $data['note']);

        return back()->with('success', 'Review recorded.');
    }

    public function execute(Request $request, ApprovalRequest $approval): RedirectResponse
    {
        $request->validate(['shift_override_reason' => 'nullable|string|max:500']);
        $this->approvals->execute($request, $approval);

        return back()->with('success', 'Approved action executed and recorded.');
    }

    public function cancel(Request $request, ApprovalRequest $approval): RedirectResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:1000']);
        $this->approvals->cancel($request, $approval, $data['reason']);

        return back()->with('success', 'Request cancelled with retained history.');
    }
}
