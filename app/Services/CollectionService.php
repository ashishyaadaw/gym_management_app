<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\StoreSale;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * "Daily collection": everything paid at the counter — membership payments and
 * store (POS) sales — flattened into one list and summed by Cash / UPI / Card.
 */
class CollectionService
{
    /** Collapse the payment `method` values into the four buckets the UI shows. */
    public static function bucket(?string $method): string
    {
        return match ($method) {
            'cash' => 'cash',
            'upi' => 'upi',
            'card', 'credit_card', 'debit_card' => 'card',
            default => 'other',
        };
    }

    /** @return Collection<int, array<string, mixed>> newest first */
    public function transactions(Carbon $from, Carbon $to): Collection
    {
        $payments = Payment::with('user:id,name', 'memberPlan.plan:id,name')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->get()
            ->map(fn (Payment $p) => [
                'id' => 'p'.$p->id,
                'time' => $p->paid_at->toIso8601String(),
                'date' => $p->paid_at->toDateString(),
                'hour' => $p->paid_at->hour,
                // No member = a lump sum entered from an old daybook on the Past Records page.
                'label' => $p->user?->name ?? ($p->user_id ? 'Member' : 'Lump sum'),
                'detail' => $p->memberPlan?->plan?->name ?? ($p->notes ?: 'Payment'),
                'type' => $p->member_plan_id ? 'membership' : 'payment',
                'method' => self::bucket($p->method),
                'amount' => (float) $p->amount,
            ]);

        $sales = StoreSale::with('items', 'member:id,name')
            ->where('status', 'completed') // voided sales are not money in the till
            ->whereBetween('sold_at', [$from, $to])
            ->get()
            ->map(fn (StoreSale $s) => [
                'id' => 's'.$s->id,
                'time' => $s->sold_at->toIso8601String(),
                'date' => $s->sold_at->toDateString(),
                'hour' => $s->sold_at->hour,
                'label' => $s->items->map(fn ($i) => "{$i->name} x{$i->quantity}")->implode(', '),
                'detail' => $s->member?->name ?? 'Walk-in',
                'type' => 'store',
                'method' => self::bucket($s->method),
                'amount' => (float) $s->total,
            ]);

        return $payments->concat($sales)->sortByDesc('time')->values();
    }

    /** @param Collection<int, array<string, mixed>> $txns */
    public function totals(Collection $txns): array
    {
        $sum = fn (string $method) => round($txns->where('method', $method)->sum('amount'), 2);

        return [
            'total' => round($txns->sum('amount'), 2),
            'cash' => $sum('cash'),
            'upi' => $sum('upi'),
            'card' => $sum('card'),
            'other' => $sum('other'),
            'count' => $txns->count(),
        ];
    }

    /** Morning 5–12, afternoon 12–17, evening the rest. */
    public function timeSplit(Collection $txns): array
    {
        $in = fn (callable $test) => round($txns->filter(fn ($t) => $test($t['hour']))->sum('amount'), 2);

        return [
            'morning' => $in(fn ($h) => $h >= 5 && $h < 12),
            'afternoon' => $in(fn ($h) => $h >= 12 && $h < 17),
            'evening' => $in(fn ($h) => $h < 5 || $h >= 17),
        ];
    }

    /** One day in full: totals, time split and the transaction list. */
    public function day(Carbon $date): array
    {
        $txns = $this->transactions($date->copy()->startOfDay(), $date->copy()->endOfDay());

        return [
            'date' => $date->toDateString(),
            'totals' => $this->totals($txns),
            'time_split' => $this->timeSplit($txns),
            'transactions' => $txns->values(),
        ];
    }

    /** The last $days days (oldest first), one summary row each. */
    public function recentDays(int $days = 7): array
    {
        $end = today();
        $txns = $this->transactions($end->copy()->subDays($days - 1)->startOfDay(), $end->copy()->endOfDay());
        $byDate = $txns->groupBy('date');

        return collect(range($days - 1, 0))->map(function (int $ago) use ($end, $byDate) {
            $d = $end->copy()->subDays($ago);

            return ['date' => $d->toDateString(), 'label' => $d->format('d M')]
                + $this->totals($byDate->get($d->toDateString(), collect()));
        })->all();
    }
}
