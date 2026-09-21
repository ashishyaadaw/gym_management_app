<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffAttendance extends Model
{
    public const STATUSES = ['present', 'half_day', 'absent', 'leave', 'holiday'];

    protected $fillable = [
        'user_id', 'work_date', 'status', 'check_in_at', 'check_out_at',
        'is_late', 'worked_minutes', 'note', 'marked_by',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date:Y-m-d',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'is_late' => 'boolean',
            'worked_minutes' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function markedBy()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    /** Clocked in but not out yet. */
    public function isOpen(): bool
    {
        return $this->check_in_at !== null && $this->check_out_at === null;
    }
}
