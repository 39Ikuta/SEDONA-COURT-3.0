<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BookingController extends Controller
{
    /**
     * Display all reservations.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status', 'scheduled');

        $query = Booking::with(['room', 'createdBy'])->orderBy('arrival_at');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $bookings = $query->paginate(15);
        $availableRooms = Room::where('is_staff_quarters', false)->orderBy('number')->get();

        return view('bookings.index', compact('bookings', 'availableRooms', 'status'));
    }

    /**
     * Store a new booking / reservation.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'guest_name' => 'required|string|max:255',
            'guest_contact' => 'nullable|string|max:50',
            'headcount' => 'required|integer|min:1',
            'rate_tier' => 'required|in:3h,6h,12h,24h,promo',
            'arrival_at' => 'required|date|after_or_equal:today',
            'notes' => 'nullable|string|max:500',
        ]);

        $room = Room::findOrFail($validated['room_id']);
        $arrival = Carbon::parse($validated['arrival_at']);
        $hours = $room->getTierHours($validated['rate_tier']);
        $departure = (clone $arrival)->addHours($hours);

        // Check for conflicting reservations on same room
        if (Booking::hasConflict($room->id, $arrival->toDateTimeString(), $departure->toDateTimeString())) {
            return back()->with('error', "Room {$room->number} is already reserved for the selected date/time.")->withInput();
        }

        Booking::create([
            'room_id' => $room->id,
            'user_id' => Auth::id() ?? 1,
            'guest_name' => $validated['guest_name'],
            'guest_contact' => $validated['guest_contact'] ?? null,
            'headcount' => $validated['headcount'],
            'rate_tier' => $validated['rate_tier'],
            'arrival_at' => $arrival,
            'departure_at' => $departure,
            'status' => 'scheduled',
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('bookings.index')
            ->with('success', "Reservation created for {$validated['guest_name']} in Room {$room->number} ({$arrival->format('M d, Y h:i A')}).");
    }

    /**
     * Cancel a booking.
     */
    public function cancel(Booking $booking): RedirectResponse
    {
        $booking->update(['status' => 'cancelled']);

        return back()->with('info', "Booking #{$booking->id} for {$booking->guest_name} has been cancelled.");
    }
}
