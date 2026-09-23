<?php

namespace App\Http\Controllers\Concerns;

use Carbon\Carbon;
use Illuminate\Http\Request;

/** ?from / ?to date-range reports and CSV exports (store sales, expenses). */
trait ReportsOnDateRange
{
    /** @return array{0: Carbon, 1: Carbon} start of ?from … end of ?to (default: today), at most a year apart. */
    private function range(Request $request): array
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);

        $to = ($request->filled('to') ? Carbon::parse($request->query('to')) : today())->endOfDay();
        $from = ($request->filled('from') ? Carbon::parse($request->query('from')) : $to->copy())->startOfDay();
        if ($to->lt($from)) {
            $to = $from->copy()->endOfDay();
        }

        if ($from->diffInDays($to) > 366) {
            abort(response()->json(['message' => 'Choose a range of a year or less.'], 422));
        }

        return [$from, $to];
    }

    /** Stop spreadsheet apps from running a cell that starts with = + - @ as a formula. */
    private function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
