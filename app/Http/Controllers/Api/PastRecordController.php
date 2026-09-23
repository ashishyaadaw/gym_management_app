<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsOnDateRange;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PastRecordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Admin: enter the gym's past payments (before this software) in bulk, list them, and undo mistakes. */
class PastRecordController extends Controller
{
    use ReportsOnDateRange;

    /** Rows per request; the page sends bigger imports in batches of this size. */
    private const MAX_ROWS = 100;

    public function __construct(private PastRecordService $records) {}

    /** Past records whose payment date falls in ?from … ?to. */
    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);

        $rows = Payment::with('user:id,name,phone', 'memberPlan.plan:id,name', 'recorder:id,name')
            ->where('source', Payment::SOURCE_HISTORY)
            ->whereBetween('paid_at', [$from, $to])
            ->orderByDesc('paid_at')->orderByDesc('id')
            ->get();

        return response()->json([
            'total' => round((float) $rows->sum('amount'), 2),
            'count' => $rows->count(),
            'data' => $rows->map(fn (Payment $p) => [
                'id' => $p->id,
                'paid_on' => $p->paid_at->toDateString(),
                'member' => $p->user?->name,
                'phone' => $p->user?->phone,
                'plan' => $p->memberPlan?->plan?->name,
                'start_date' => $p->memberPlan?->start_date?->toDateString(),
                'end_date' => $p->memberPlan?->end_date?->toDateString(),
                'amount' => (float) $p->amount,
                'method' => $p->method,
                'reference' => $p->transaction_reference,
                'notes' => $p->notes,
                'recorded_by' => $p->recorder?->name,
                'recorded_at' => $p->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * Save a batch of rows. Each row stands alone: good rows are saved, bad rows come back with their error,
     * so one typo doesn't block a whole year's import.
     */
    public function store(Request $request)
    {
        $request->validate(['rows' => 'required|array|min:1|max:'.self::MAX_ROWS]);

        $results = [];
        foreach ($request->input('rows') as $i => $row) {
            $key = is_array($row) ? ($row['key'] ?? $i) : $i;
            try {
                $data = $this->validateRow(is_array($row) ? $row : []);
                $saved = $this->records->record($data, $request->user());
                $results[] = [
                    'key' => $key,
                    'ok' => true,
                    'payment_id' => $saved['payment']->id,
                    'member' => $saved['member']?->name,
                    'member_created' => $saved['member_created'],
                    'amount' => (float) $saved['payment']->amount,
                ];
            } catch (ValidationException $e) {
                $results[] = ['key' => $key, 'ok' => false, 'error' => collect($e->errors())->flatten()->implode(' ')];
            }
        }

        $saved = collect($results)->where('ok', true);

        return response()->json([
            'saved' => $saved->count(),
            'failed' => count($results) - $saved->count(),
            'members_created' => $saved->where('member_created', true)->count(),
            'amount' => round($saved->sum('amount'), 2),
            'results' => $results,
        ]);
    }

    public function destroy(Payment $payment)
    {
        // Only past records can be undone here; live payments have their own refund flow.
        abort_unless($payment->source === Payment::SOURCE_HISTORY, 404);

        $this->records->undo($payment);

        return response()->json(['message' => 'Past record removed.']);
    }

    private function validateRow(array $row): array
    {
        return Validator::make($row, [
            'paid_on' => 'required|date|before_or_equal:today|after:2000-01-01',
            'name' => 'nullable|string|max:255',
            'phone' => ['nullable', 'regex:/^\d{10}$/'],
            'membership_plan_id' => 'nullable|exists:membership_plans,id',
            'start_date' => 'nullable|date|after:2000-01-01',
            'amount' => 'nullable|numeric|min:0.01|max:9999999',
            'method' => 'required|in:cash,upi,card,bank_transfer,other',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ], [
            'paid_on.required' => 'Payment date is missing.',
            'paid_on.date' => 'Payment date is not a valid date.',
            'paid_on.before_or_equal' => 'Payment date cannot be in the future.',
            'phone.regex' => 'Phone must be exactly 10 digits.',
            'membership_plan_id.exists' => 'Unknown plan.',
            'method.in' => 'Payment mode must be cash, UPI, card, bank transfer or other.',
        ])->validate();
    }
}
