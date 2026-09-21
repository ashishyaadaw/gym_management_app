<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class StaffProfile extends Model
{
    public const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    protected $fillable = [
        'user_id', 'employee_code', 'designation', 'joining_date', 'leaving_date',
        'pay_type', 'base_salary', 'shift_start', 'shift_end', 'weekly_off', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'joining_date' => 'date:Y-m-d',
            'leaving_date' => 'date:Y-m-d',
            'base_salary' => 'decimal:2',
            'weekly_off' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isHourly(): bool
    {
        return $this->pay_type === 'hourly';
    }

    /** A salary of 0 means "not paid through payroll" (e.g. the owner) — attendance is still tracked. */
    public function isOnPayroll(): bool
    {
        return (float) $this->base_salary > 0;
    }

    /** True when $date falls between the joining date and (if they have left) the leaving date. */
    public function isEmployedOn(Carbon $date): bool
    {
        $d = $date->toDateString();

        return $this->joining_date->toDateString() <= $d
            && ($this->leaving_date === null || $this->leaving_date->toDateString() >= $d);
    }

    /** Employed for at least one day of the month that starts on $month. */
    public function isEmployedDuring(Carbon $month): bool
    {
        return $this->joining_date->toDateString() <= $month->copy()->endOfMonth()->toDateString()
            && ($this->leaving_date === null || $this->leaving_date->toDateString() >= $month->copy()->startOfMonth()->toDateString());
    }

    public function isWeeklyOff(Carbon $date): bool
    {
        return $date->dayOfWeek === $this->weekly_off;
    }

    /** Next free code: EMP-001, EMP-002 … */
    public static function nextCode(): string
    {
        $n = (int) static::max('id') + 1;
        do {
            $code = 'EMP-'.str_pad((string) $n++, 3, '0', STR_PAD_LEFT);
        } while (static::where('employee_code', $code)->exists());

        return $code;
    }
}
