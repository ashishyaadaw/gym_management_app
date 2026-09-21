<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassSchedule extends Model
{
    protected $fillable = [
        'gym_class_id', 'trainer_id', 'room', 'start_time', 'end_time',
        'capacity_override', 'status', 'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ];
    }

    public function gymClass()
    {
        return $this->belongsTo(GymClass::class, 'gym_class_id');
    }

    public function trainer()
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function confirmedBookings()
    {
        return $this->bookings()->where('status', 'confirmed');
    }

    public function capacity(): int
    {
        return $this->capacity_override ?? $this->gymClass->capacity;
    }

    public function seatsTaken(): int
    {
        return $this->confirmedBookings()->count();
    }

    public function hasAvailableSeats(): bool
    {
        return $this->seatsTaken() < $this->capacity();
    }
}
