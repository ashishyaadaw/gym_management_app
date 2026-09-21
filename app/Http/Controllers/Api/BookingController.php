<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ClassSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Booking::with(['classSchedule.gymClass', 'classSchedule.trainer', 'user']);

        if ($user->isMember()) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('class_schedule_id')) {
            $query->where('class_schedule_id', $request->class_schedule_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->paginate($request->get('per_page', 20)));
    }

    /** Member books a class session; enforces capacity + active plan credits. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'class_schedule_id' => 'required|exists:class_schedules,id',
        ]);

        $user = $request->user();

        return DB::transaction(function () use ($user, $data) {
            $schedule = ClassSchedule::lockForUpdate()->findOrFail($data['class_schedule_id']);

            if ($schedule->status !== 'scheduled') {
                return response()->json(['message' => 'This class session is not open for booking.'], 422);
            }

            if (Booking::where('user_id', $user->id)->where('class_schedule_id', $schedule->id)
                ->whereIn('status', ['confirmed', 'waitlisted'])->exists()) {
                return response()->json(['message' => 'You already have a booking for this class.'], 422);
            }

            $activePlan = $user->memberPlans()->where('status', 'active')->latest()->first();
            if (! $activePlan) {
                return response()->json(['message' => 'An active membership plan is required to book classes.'], 403);
            }
            if (! is_null($activePlan->remaining_credits) && $activePlan->remaining_credits <= 0) {
                return response()->json(['message' => 'No remaining class credits on your plan.'], 403);
            }

            $status = $schedule->hasAvailableSeats() ? 'confirmed' : 'waitlisted';

            $booking = Booking::create([
                'user_id' => $user->id,
                'class_schedule_id' => $schedule->id,
                'status' => $status,
            ]);

            if ($status === 'confirmed' && ! is_null($activePlan->remaining_credits)) {
                $activePlan->decrement('remaining_credits');
            }

            return response()->json($booking->load('classSchedule.gymClass'), 201);
        });
    }

    public function show(Booking $booking)
    {
        return response()->json($booking->load('classSchedule.gymClass', 'classSchedule.trainer', 'user'));
    }

    /** Cancel a booking; promotes the next waitlisted member if a seat frees up. */
    public function cancel(Request $request, Booking $booking)
    {
        return DB::transaction(function () use ($booking, $request) {
            $wasConfirmed = $booking->status === 'confirmed';

            $booking->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $request->get('reason'),
            ]);

            if ($wasConfirmed) {
                $next = Booking::where('class_schedule_id', $booking->class_schedule_id)
                    ->where('status', 'waitlisted')->oldest()->first();

                if ($next) {
                    $next->update(['status' => 'confirmed']);
                }
            }

            return response()->json($booking);
        });
    }
}
