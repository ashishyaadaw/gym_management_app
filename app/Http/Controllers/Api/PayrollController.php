<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payslip;
use App\Models\StaffProfile;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Admin: monthly salary — preview, generate, adjust, mark paid. Staff: their own paid payslips. */
class PayrollController extends Controller
{
    public function __construct(private PayrollService $payroll) {}

    /** Everyone employed in the month, with their payslip if one exists, else what it would be. */
    public function index(Request $request)
    {
        $month = PayrollService::parseMonth($request->query('month', now()->subMonthNoOverflow()->format('Y-m')));

        $slips = Payslip::whereDate('month', $month)->get()->keyBy('user_id');

        $rows = StaffProfile::with('user:id,name,role,is_active')->get()
            ->filter(fn (StaffProfile $p) => $p->user && $p->isEmployedDuring($month) && ($p->isOnPayroll() || $slips->has($p->user_id)))
            ->sortBy(fn (StaffProfile $p) => strtolower($p->user->name))
            ->map(function (StaffProfile $p) use ($month, $slips) {
                $figures = $this->payroll->compute($p, $month);
                $slip = $slips->get($p->user_id);
                $stale = $slip && ! $slip->isPaid()
                    && (abs((float) $slip->gross_pay - $figures['gross_pay']) > 0.005 || abs((float) $slip->late_deduction - $figures['late_deduction']) > 0.005);

                return [
                    'user_id' => $p->user_id,
                    'name' => $p->user->name,
                    'role' => $p->user->role,
                    'is_active' => $p->user->is_active,
                    'employee_code' => $p->employee_code,
                    'designation' => $p->designation,
                    'pay_type' => $p->pay_type,
                    'base_salary' => (float) $p->base_salary,
                    'preview' => $slip ? null : collect($figures)->except('warnings')->all(),
                    'warnings' => $slip?->isPaid() ? [] : $figures['warnings'],
                    'stale' => (bool) $stale,
                    'slip' => $slip ? $slip->append('slip_number') : null,
                ];
            })->values();

        $issued = $rows->pluck('slip')->filter();

        return response()->json([
            'month' => $month->format('Y-m'),
            'label' => $month->format('F Y'),
            'can_generate' => $this->payroll->monthHasEnded($month),
            'ends_on' => $month->copy()->endOfMonth()->toDateString(),
            'rows' => $rows,
            'totals' => [
                'staff' => $rows->count(),
                'generated' => $issued->count(),
                'net' => round((float) $issued->sum('net_pay'), 2),
                'paid' => round((float) $issued->where('status', 'paid')->sum('net_pay'), 2),
                'pending' => round((float) $issued->where('status', 'draft')->sum('net_pay'), 2),
            ],
        ]);
    }

    /** Create / refresh draft payslips for the month (all staff, or the given user_ids). */
    public function generate(Request $request)
    {
        $data = $request->validate([
            'month' => 'required|string',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer',
            'treat_unmarked_absent' => 'sometimes|boolean',
        ]);

        return response()->json($this->payroll->generate(
            PayrollService::parseMonth($data['month']),
            $data['user_ids'] ?? null,
            (bool) ($data['treat_unmarked_absent'] ?? false),
            $request->user(),
        ));
    }

    /** Bonus / other deduction / note on a draft payslip. */
    public function update(Request $request, Payslip $payslip)
    {
        $data = $request->validate([
            'bonus' => 'required|numeric|min:0|max:9999999',
            'other_deduction' => 'required|numeric|min:0|max:9999999',
            'adjustment_note' => 'nullable|string|max:255',
        ]);

        $slip = $this->payroll->adjust($payslip, (float) $data['bonus'], (float) $data['other_deduction'], $data['adjustment_note'] ?? null);

        return response()->json($slip->append('slip_number'));
    }

    public function pay(Request $request, Payslip $payslip)
    {
        $data = $request->validate([
            'paid_on' => 'nullable|date|before_or_equal:today',
            'payment_method' => 'required|in:cash,upi,bank_transfer,cheque',
            'payment_reference' => 'nullable|string|max:255',
        ]);

        $slip = $this->payroll->pay(
            $payslip,
            isset($data['paid_on']) ? Carbon::parse($data['paid_on']) : today(),
            $data['payment_method'],
            $data['payment_reference'] ?? null,
        );

        return response()->json($slip->append('slip_number'));
    }

    /** Discard a draft (a paid payslip is a record and stays). */
    public function destroy(Payslip $payslip)
    {
        if ($payslip->isPaid()) {
            throw ValidationException::withMessages(['status' => ['A paid payslip cannot be deleted.']]);
        }

        $payslip->delete();

        return response()->json(['message' => 'Draft deleted.']);
    }

    /** A staff member's own paid payslips, newest first. */
    public function mine(Request $request)
    {
        return response()->json(
            Payslip::where('user_id', $request->user()->id)->where('status', 'paid')
                ->orderByDesc('month')->get()
                ->each(fn (Payslip $s) => $s->append('slip_number'))
        );
    }
}
