<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsOnDateRange;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Payslip;
use App\Services\CollectionService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Daily expenses: the front desk records money paid out; the admin also sees it against income and payroll.
 * Receptionists can fix or delete only their own entries, and only on the day they made them.
 */
class ExpenseController extends Controller
{
    use ReportsOnDateRange;

    public function __construct(private CollectionService $collection) {}

    /** Expenses in ?from … ?to (optionally one ?category), plus totals for the range. */
    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);
        $user = $request->user();

        $expenses = $this->query($request, $from, $to)
            ->with('recorder:id,name')
            ->orderByDesc('spent_on')->orderByDesc('id')
            ->get();

        $byDay = $expenses->groupBy(fn (Expense $e) => $e->spent_on->toDateString());
        $total = round((float) $expenses->sum('amount'), 2);

        $summary = [
            'total' => $total,
            'count' => $expenses->count(),
            'by_method' => collect(Expense::METHODS)->map(
                fn ($label, $key) => round((float) $expenses->where('method', $key)->sum('amount'), 2)
            ),
            'by_category' => $expenses->groupBy('category')->map(fn ($rows, $key) => [
                'key' => $key,
                'label' => Expense::CATEGORIES[$key] ?? $key,
                'total' => round((float) $rows->sum('amount'), 2),
                'count' => $rows->count(),
            ])->sortByDesc('total')->values(),
            'by_day' => collect(CarbonPeriod::create($from, $to))->map(fn (Carbon $d) => [
                'date' => $d->toDateString(),
                'label' => $d->format('d M'),
                'total' => round((float) $byDay->get($d->toDateString(), collect())->sum('amount'), 2),
            ])->values(),
        ];

        // Profit view is the owner's: income for the same dates, salaries paid through Payroll, and what is left.
        if ($user->isAdmin() && ! $request->filled('category')) {
            $income = $this->collection->totals($this->collection->transactions($from, $to))['total'];
            $salaries = round((float) Payslip::where('status', 'paid')
                ->whereBetween('paid_on', [$from->toDateString(), $to->toDateString()])->sum('net_pay'), 2);

            $summary += [
                'income' => $income,
                'salaries' => $salaries,
                'net' => round($income - $total - $salaries, 2),
            ];
        }

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'summary' => $summary,
            'data' => $expenses->map(fn (Expense $e) => $this->row($e, $user))->values(),
        ]);
    }

    public function store(Request $request)
    {
        $expense = Expense::create($this->validated($request) + ['recorded_by' => $request->user()->id]);

        return response()->json($this->row($expense->load('recorder:id,name'), $request->user()), 201);
    }

    public function update(Request $request, Expense $expense)
    {
        $this->authorizeEdit($request, $expense);

        $expense->update($this->validated($request));

        return response()->json($this->row($expense->load('recorder:id,name'), $request->user()));
    }

    public function destroy(Request $request, Expense $expense)
    {
        $this->authorizeEdit($request, $expense);

        $expense->delete();

        return response()->json(['message' => 'Expense deleted.']);
    }

    /** Download the expenses in a range as a CSV (opens in Excel). */
    public function export(Request $request)
    {
        [$from, $to] = $this->range($request);

        $expenses = $this->query($request, $from, $to)
            ->with('recorder:id,name')
            ->orderBy('spent_on')->orderBy('id')
            ->get();

        $filename = 'expenses-'.$from->toDateString().'_to_'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($expenses) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8 text correctly

            fputcsv($out, ['Date', 'Category', 'Description', 'Paid to', 'Payment', 'Reference', 'Amount', 'Recorded by', 'Notes']);

            foreach ($expenses as $e) {
                fputcsv($out, [
                    $e->spent_on->toDateString(),
                    Expense::CATEGORIES[$e->category] ?? $e->category,
                    $this->csvSafe($e->description),
                    $this->csvSafe($e->paid_to ?? ''),
                    Expense::METHODS[$e->method] ?? $e->method,
                    $this->csvSafe($e->reference ?? ''),
                    number_format($e->amount, 2, '.', ''),
                    $this->csvSafe($e->recorder?->name ?? ''),
                    $this->csvSafe($e->notes ?? ''),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ---------- Helpers ----------

    private function query(Request $request, Carbon $from, Carbon $to): Builder
    {
        $request->validate(['category' => ['nullable', Rule::in(array_keys(Expense::CATEGORIES))]]);

        return Expense::query()
            ->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->query('category')));
    }

    private function row(Expense $e, $user): array
    {
        return [
            'id' => $e->id,
            'spent_on' => $e->spent_on->toDateString(),
            'category' => $e->category,
            'category_label' => Expense::CATEGORIES[$e->category] ?? $e->category,
            'description' => $e->description,
            'amount' => $e->amount,
            'method' => $e->method,
            'paid_to' => $e->paid_to,
            'reference' => $e->reference,
            'notes' => $e->notes,
            'recorded_by' => $e->recorder?->name,
            'recorded_at' => $e->created_at?->toIso8601String(),
            'can_edit' => $e->editableBy($user),
        ];
    }

    private function authorizeEdit(Request $request, Expense $expense): void
    {
        if (! $expense->editableBy($request->user())) {
            abort(response()->json(['message' => 'Only the owner can change this entry. You can fix your own entries on the day you record them.'], 403));
        }
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'spent_on' => 'required|date|before_or_equal:today|after:2000-01-01',
            'category' => ['required', Rule::in(array_keys(Expense::CATEGORIES))],
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01|max:99999999',
            'method' => ['required', Rule::in(array_keys(Expense::METHODS))],
            'paid_to' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ], [
            'spent_on.before_or_equal' => 'The expense date cannot be in the future.',
            'amount.min' => 'Enter an amount greater than zero.',
        ]);
    }
}
