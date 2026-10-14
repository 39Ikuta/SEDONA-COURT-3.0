<?php

namespace App\Http\Controllers;

use App\Models\Folio;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DailyCheckInController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['date' => 'nullable|date_format:Y-m-d']);
        $date = $filters['date'] ?? CarbonImmutable::now('Asia/Manila')->toDateString();
        $day = CarbonImmutable::parse($date, 'Asia/Manila')->startOfDay();
        $start = $day->setTimezone(config('app.timezone'));
        $end = $start->addDay();
        $dateCode = $day->format('Ymd');

        $query = Folio::whereHas('room', fn (Builder $rooms): Builder => $rooms->where('is_staff_quarters', false))
            ->where(function (Builder $folios) use ($dateCode, $start, $end): void {
                $folios->where('transaction_id', 'like', 'SCTI-'.$dateCode.'-%')
                    ->orWhere(function (Builder $legacy) use ($start, $end): void {
                        $legacy->where(fn (Builder $references): Builder => $references->whereNull('transaction_id')
                            ->orWhere('transaction_id', 'not like', 'SCTI-________-%'))
                            ->where('folios.created_at', '>=', $start)->where('folios.created_at', '<', $end);
                    });
            });
        $valid = (clone $query)->where('folios.status', '!=', 'voided');
        $counts = ['registered' => (clone $query)->count(), 'stays' => (clone $valid)->count(),
            'guests' => (int) (clone $valid)->join('guests', 'guests.id', '=', 'folios.guest_id')->sum('guests.headcount'),
            'cancelled' => (clone $query)->where('folios.status', 'voided')->count()];
        $lastNumber = (int) DB::table('document_sequences')->where('name', 'scti:'.$dateCode)->value('last_number');
        $folios = $query->with(['room', 'guest', 'cashier'])->orderBy('folios.created_at')->orderBy('folios.id')
            ->paginate(30)->withQueryString();

        return view('checkins.index', compact('date', 'day', 'folios', 'counts', 'lastNumber'));
    }
}
