<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Booking;
use App\Models\ClassSchedule;
use App\Models\Equipment;
use App\Models\MemberPlan;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\CollectionService;
use App\Services\MemberStatusService;
use App\Services\StaffAttendanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private CollectionService $collection,
        private MemberStatusService $statuses,
        private StaffAttendanceService $staffAttendance,
    ) {}

    /**
     * Front-desk overview for admin + receptionist: today's collection, member
     * status, who is about to expire / has lapsed / has gone quiet, and check-in traffic.
     */
    public function staffSummary(Request $request)
    {
        $members = $this->statuses->members();
        $inactiveBefore = now()->subDays(config('gym.inactive_days'));
        $take = fn ($rows) => $rows->take(8)->values();

        $monthTxns = $this->collection->transactions(now()->startOfMonth(), now()->endOfMonth());

        return response()->json([
            'staff_today' => $request->user()->isAdmin() ? $this->staffAttendance->todaySummary() : null,
            'members' => $this->statuses->counts($members),
            'expiring_members' => $take($members->where('status', 'expiring')->sortBy('days_left')),
            'expired_members' => $take($members->where('status', 'expired')->sortByDesc('days_left')),
            'inactive_members' => $take($members
                ->whereIn('status', ['active', 'expiring'])
                // Someone who joined recently hasn't had time to go quiet yet: measure from their last visit, else their join date.
                ->filter(fn ($m) => Carbon::parse($m['last_visit'] ?? $m['joined_on'])->lt($inactiveBefore))),
            'low_stock' => Product::where('is_active', true)->where('stock', '<=', config('gym.low_stock'))
                ->orderBy('stock')->orderBy('name')->limit(8)->get(['id', 'name', 'stock']),
            'collection' => $this->collection->day(today()),
            'last_7_days' => $this->collection->recentDays(7),
            'month_revenue' => $this->collection->totals($monthTxns)['total'],
            'pending_dues' => (float) Payment::where('status', 'pending')->sum('amount'),
            'checkins_today' => Attendance::whereBetween('check_in_time', [today()->startOfDay(), today()->endOfDay()])->count(),
        ]);
    }

    /** One day's collection, for the 7-day table's "View" button. */
    public function collection(Request $request)
    {
        $request->validate(['date' => 'nullable|date']);

        return response()->json($this->collection->day(
            $request->filled('date') ? Carbon::parse($request->query('date')) : today()
        ));
    }

    /** Admin overview: revenue, membership growth, attendance, equipment status. */
    public function adminSummary(Request $request)
    {
        $from = Carbon::parse($request->get('from', now()->subDays(30)->toDateString()))->startOfDay();
        // A bare date means midnight, which would drop everything that happened on the last day.
        $to = Carbon::parse($request->get('to', now()->toDateString()))->endOfDay();

        $periodTxns = $this->collection->transactions($from, $to);

        return response()->json([
            'total_members' => User::where('role', 'member')->count(),
            'active_members' => MemberPlan::where('status', 'active')->distinct('user_id')->count('user_id'),
            'total_trainers' => User::where('role', 'trainer')->count(),
            'revenue_period' => $this->collection->totals($periodTxns)['total'],
            'pending_invoices' => Payment::where('status', 'pending')->count(),
            'pending_amount' => Payment::where('status', 'pending')->sum('amount'),
            'classes_this_week' => ClassSchedule::whereBetween('start_time', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'bookings_period' => Booking::whereBetween('created_at', [$from, $to])->count(),
            'checkins_period' => Attendance::whereBetween('check_in_time', [$from, $to])->count(),
            'equipment_needing_maintenance' => Equipment::where('status', 'under_maintenance')
                ->orWhere('next_maintenance_date', '<=', now())->count(),
            'revenue_by_day' => $periodTxns->groupBy('date')
                ->map(fn ($rows, $date) => ['date' => $date, 'total' => round($rows->sum('amount'), 2)])
                ->sortKeys()->values(),
            'revenue_by_month' => $this->revenueByMonth(),
            'method_split' => $this->collection->totals(
                $this->collection->transactions(now()->subMonths(5)->startOfMonth(), now()->endOfMonth())
            ),
        ]);
    }

    /** Revenue and new members for each of the last six months (oldest first). */
    private function revenueByMonth(): array
    {
        $txns = $this->collection->transactions(now()->subMonths(5)->startOfMonth(), now()->endOfMonth())
            ->groupBy(fn ($t) => substr($t['date'], 0, 7));
        $joined = User::where('role', 'member')
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->pluck('created_at')
            ->countBy(fn ($d) => $d->format('Y-m'));

        return collect(range(5, 0))->map(function (int $ago) use ($txns, $joined) {
            $month = now()->startOfMonth()->subMonths($ago);
            $key = $month->format('Y-m');

            return [
                'label' => $month->format('M'),
                'revenue' => round($txns->get($key, collect())->sum('amount'), 2),
                'new_members' => $joined->get($key, 0),
            ];
        })->all();
    }

    /** Trainer overview: upcoming classes, roster sizes. */
    public function trainerSummary(Request $request)
    {
        $trainer = $request->user();

        $upcoming = $trainer->classSchedules()
            ->where('start_time', '>=', now())
            ->where('status', 'scheduled')
            ->withCount('confirmedBookings')
            ->with('gymClass')
            ->orderBy('start_time')
            ->take(10)
            ->get();

        return response()->json([
            'upcoming_classes' => $upcoming,
            'classes_taught_total' => $trainer->classSchedules()->count(),
        ]);
    }

    /** Member overview: plan status, upcoming bookings, payment history. */
    public function memberSummary(Request $request)
    {
        $member = $request->user();

        return response()->json([
            'active_plan' => $member->memberPlans()->where('status', 'active')->with('plan')->latest()->first(),
            'upcoming_bookings' => $member->bookings()
                ->whereIn('status', ['confirmed', 'waitlisted'])
                ->whereHas('classSchedule', fn ($q) => $q->where('start_time', '>=', now()))
                ->with('classSchedule.gymClass', 'classSchedule.trainer')
                ->get(),
            'recent_payments' => $member->payments()->latest()->take(5)->get(),
            'attendance_count_30d' => $member->attendances()->where('check_in_time', '>=', now()->subDays(30))->count(),
        ]);
    }
}
