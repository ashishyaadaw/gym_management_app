<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GymClass extends Model
{
    protected $table = 'gym_classes';

    protected $fillable = [
        'name', 'description', 'category', 'duration_minutes', 'capacity', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function schedules()
    {
        return $this->hasMany(ClassSchedule::class);
    }
}
