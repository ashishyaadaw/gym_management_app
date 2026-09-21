<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Booking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Used by receptionists/admins for front-desk check-in, and by trainers
 * for marking class attendance.
 */
class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Attendance::with('user', 'booking.classSchedule.gymClass');

        if ($request->user()->isMember()) {
            $query->where('user_id', $request->user()->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('from')) {
            $query->where('check_in_time', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('check_in_time', '<=', $request->to);
        }

        return response()->json($query->latest('check_in_time')->paginate($request->get('per_page', 30)));
    }

    /**
     * Front-desk board for one day (?date=, default today): every check-in plus
     * a headcount per hour (6am–10pm) to show when the gym is busiest.
     */
    public function day(Request $request)
    {
        $request->validate(['date' => 'nullable|date']);
        $date = $request->filled('date') ? Carbon::parse($request->query('date')) : today();

        $records = Attendance::with('user:id,name,phone')
            ->whereBetween('check_in_time', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->latest('check_in_time')
            ->get();

        $peak = array_fill_keys(range(6, 22), 0);
        foreach ($records as $r) {
            $hour = $r->check_in_time->hour;
            if (isset($peak[$hour])) {
                $peak[$hour]++;
            }
        }

        return response()->json([
            'date' => $date->toDateString(),
            'count' => $records->count(),
            'inside' => $records->whereNull('check_out_time')->count(),
            'peak_hours' => $peak,
            'data' => $records,
        ]);
    }

    /** Front-desk check-in via member ID or email (gym_visit) or booking_id (class). */
    public function checkIn(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required_without:booking_id|exists:users,id',
            'booking_id' => 'nullable|exists:bookings,id',
        ]);

        $userId = $data['user_id'] ?? Booking::findOrFail($data['booking_id'])->user_id;

        $person = User::findOrFail($userId);
        if (! $person->is_active) {
            return response()->json(['message' => $person->name.' is deactivated and cannot be checked in.'], 422);
        }

        $attendance = Attendance::create([
            'user_id' => $userId,
            'booking_id' => $data['booking_id'] ?? null,
            'checked_in_by' => $request->user()->id,
            'type' => isset($data['booking_id']) ? 'class' : 'gym_visit',
            'check_in_time' => now(),
        ]);

        if (isset($data['booking_id'])) {
            Booking::where('id', $data['booking_id'])->update(['status' => 'attended']);
        }

        return response()->json($attendance->load('user'), 201);
    }

    public function checkOut(Attendance $attendance)
    {
        $attendance->update(['check_out_time' => now()]);

        return response()->json($attendance);
    }
}
