<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'role', 'avatar', 'gender',
        'date_of_birth', 'address', 'emergency_contact_name',
        'emergency_contact_phone', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    // ---- Role helpers ----
    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isTrainer(): bool { return $this->role === 'trainer'; }
    public function isMember(): bool { return $this->role === 'member'; }
    public function isReceptionist(): bool { return $this->role === 'receptionist'; }

    // ---- Relationships ----
    public function trainerProfile()
    {
        return $this->hasOne(TrainerProfile::class);
    }

    /** Employment details (joining date, pay, shift) — present for people on the gym's staff. */
    public function staffProfile()
    {
        return $this->hasOne(StaffProfile::class);
    }

    /** Body, fitness and health details — present for members whose details have been filled in. */
    public function memberProfile()
    {
        return $this->hasOne(MemberProfile::class);
    }

    public function memberPlans()
    {
        return $this->hasMany(MemberPlan::class);
    }

    public function activePlan()
    {
        return $this->hasOne(MemberPlan::class)->where('status', 'active')->latestOfMany();
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // Classes taught, when role = trainer
    public function classSchedules()
    {
        return $this->hasMany(ClassSchedule::class, 'trainer_id');
    }
}
