<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsOnDateRange;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Services\CollectionService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Collection report for any date range (up to a year): totals, day-wise and month-wise rows,
 * split by payment mode and by source, with the day's expenses alongside so the net is visible.
 */
class CollectionReportController extends Controller
{
    use ReportsOnDateRange;

    public function __construct(private CollectionService $collection) {}

    public function report(Request $request)
    {
        [$from, $to] = $this->range($request);

        $txns = $this->collection->transactions($from, $to);
        $expenses = $this->expensesByDay($from, $to);
        $days = $this->days($from, $to, $txns, $expenses);
        $totals = $this->collection->totals($txns);
        $spent = round($expenses->sum(), 2);

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'totals' => $totals + [
                'expenses' => $spent,
                'net' => round($totals['total'] - $spent, 2),
                'days' => count($days),
                'average_per_day' => count($days) ? round($totals['total'] / count($days), 2) : 0,
                'best_day' => collect($days)->sortByDesc('total')->first(fn ($d) => $d['total'] > 0),
            ],
            'by_type' => [
                'membership' => round($txns->where('type', 'membership')->sum('amount'), 2),
                'store' => round($txns->where('type', 'store')->sum('amount'), 2),
                'payment' => round($txns->where('type', 'payment')->sum('amount'), 2),
            ],
            'by_day' => $days,
            'by_month' => $this->months($days),
        ]);
    }

    /** CSV: ?type=days (one row per day, default) or ?type=transactions (every payment and sale). */
    public function export(Request $request)
    {
        [$from, $to] = $this->range($request);
        $type = $request->query('type') === 'transactions' ? 'transactions' : 'days';

        $txns = $this->collection->transactions($from, $to);
        $filename = 'collection-'.$type.'-'.$from->toDateString().'_to_'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($type, $txns, $from, $to) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8 names correctly

            if ($type === 'transactions') {
                fputcsv($out, ['Date', 'Time', 'Source', 'Member / items', 'Detail', 'Payment', 'Amount']);
                foreach ($txns->sortBy('time') as $t) {
                    $at = Carbon::parse($t['time']);
                    fputcsv($out, [
                        $at->toDateString(), $at->format('H:i'), $t['type'],
                        $this->csvSafe((string) $t['label']), $this->csvSafe((string) $t['detail']),
                        $t['method'], number_format($t['amount'], 2, '.', ''),
                    ]);
                }
            } else {
                fputcsv($out, ['Date', 'Collection', 'Cash', 'UPI', 'Card', 'Other', 'Transactions', 'Expenses', 'Net']);
                foreach ($this->days($from, $to, $txns, $this->expensesByDay($from, $to)) as $d) {
                    fputcsv($out, [
                        $d['date'], $d['total'], $d['cash'], $d['upi'], $d['card'], $d['other'], $d['count'], $d['expenses'], $d['net'],
                    ]);
                }
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ---------- Helpers ----------

    /** @return Collection<string, float> date => amount spent */
    private function expensesByDay(Carbon $from, Carbon $to): Collection
    {
        return Expense::whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])
            ->get(['spent_on', 'amount'])
            ->groupBy(fn (Expense $e) => $e->spent_on->toDateString())
            ->map(fn ($rows) => (float) $rows->sum('amount'));
    }

    /** One row per day in the range (days with nothing collected included), oldest first. */
    private function days(Carbon $from, Carbon $to, Collection $txns, Collection $expenses): array
    {
        $byDate = $txns->groupBy('date');

        return collect(CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()))
            ->map(function (Carbon $d) use ($byDate, $expenses) {
                $key = $d->toDateString();
                $totals = $this->collection->totals($byDate->get($key, collect()));
                $spent = round($expenses->get($key, 0), 2);

                return ['date' => $key, 'label' => $d->format('d M'), 'weekday' => $d->format('D')]
                    + $totals
                    + ['expenses' => $spent, 'net' => round($totals['total'] - $spent, 2)];
            })->values()->all();
    }

    /** Day rows rolled up by calendar month. */
    private function months(array $days): array
    {
        return collect($days)
            ->groupBy(fn ($d) => substr($d['date'], 0, 7))
            ->map(function (Collection $rows, string $month) {
                $sum = fn (string $k) => round($rows->sum($k), 2);

                return [
                    'month' => $month,
                    'label' => Carbon::parse($month.'-01')->format('M Y'),
                    'days' => $rows->count(),
                    'total' => $sum('total'), 'cash' => $sum('cash'), 'upi' => $sum('upi'),
                    'card' => $sum('card'), 'other' => $sum('other'), 'count' => (int) $rows->sum('count'),
                    'expenses' => $sum('expenses'), 'net' => $sum('net'),
                ];
            })->values()->all();
    }
}
