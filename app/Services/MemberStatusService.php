<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Works out whether a member is active / expiring / expired from their plans,
 * and lists members with that status plus last visit and pending dues.
 */
class MemberStatusService
{
    /** @return array{0: string, 1: int|null} [status, days until expiry (negative = days since)] */
    public function classify(?Carbon $endsOn, bool $openEnded): array
    {
        if ($openEnded) {
            return ['active', null]; // e.g. pay-per-class credits, no end date
        }
        if (! $endsOn) {
            return ['none', null];
        }

        $days = (int) today()->diffInDays($endsOn->copy()->startOfDay(), false);

        if ($days < 0) {
            return ['expired', $days];
        }

        return [$days <= config('gym.expiring_days') ? 'expiring' : 'active', $days];
    }

    /**
     * Every member (optionally filtered by name/phone), newest first, each as a plain array.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function members(?string $search = null): Collection
    {
        $members = User::query()
            ->where('role', 'member')
            ->when($search, function ($q) use ($search) {
                $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"));
            })
            ->withMax(['memberPlans as ends_on' => fn ($q) => $q->whereIn('status', ['active', 'expired'])], 'end_date')
            ->withCount(['memberPlans as open_plans' => fn ($q) => $q->where('status', 'active')->whereNull('end_date')])
            ->withMax('attendances as last_visit', 'check_in_time')
            ->withSum(['payments as due' => fn ($q) => $q->where('status', 'pending')], 'amount')
            ->with('activePlan.plan:id,name')
            ->latest('id')
            ->get();

        return $members->map(function (User $m) {
            [$status, $days] = $this->classify(
                $m->ends_on ? Carbon::parse($m->ends_on) : null,
                $m->open_plans > 0,
            );

            // A deactivated account is its own group, whatever its plan says.
            if (! $m->is_active) {
                $status = 'deactivated';
            }

            return [
                'id' => $m->id,
                'name' => $m->name,
                'phone' => $m->phone,
                'is_active' => $m->is_active,
                'plan' => $m->activePlan?->plan?->name,
                'ends_on' => $m->ends_on,
                'days_left' => $days,
                'status' => $status,
                'last_visit' => $m->last_visit,
                'joined_on' => $m->created_at?->toDateTimeString(),
                'due' => (float) ($m->due ?? 0),
            ];
        });
    }

    /** @param Collection<int, array<string, mixed>> $members */
    public function counts(Collection $members): array
    {
        $by = $members->countBy('status');

        return [
            'total' => $members->count(),
            'active' => $by->get('active', 0),
            'expiring' => $by->get('expiring', 0),
            'expired' => $by->get('expired', 0),
            'none' => $by->get('none', 0),
            'deactivated' => $by->get('deactivated', 0),
        ];
    }
}
