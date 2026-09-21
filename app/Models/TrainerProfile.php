<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainerProfile extends Model
{
    protected $fillable = [
        'user_id', 'specialization', 'bio', 'experience_years',
        'hourly_rate', 'certifications', 'availability',
    ];

    protected function casts(): array
    {
        return [
            'certifications' => 'array',
            'availability' => 'array',
            'hourly_rate' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
