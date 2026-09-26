<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** A one-time link a new member opens to register themselves (without a plan). */
class RegistrationLink extends Model
{
    protected $fillable = ['token', 'label', 'created_by', 'expires_at', 'used_at', 'member_id', 'revoked_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** A fresh link, valid for gym.registration_link_hours. */
    public static function issue(User $by, ?string $label): self
    {
        return self::create([
            'token' => Str::random(40),
            'label' => $label,
            'created_by' => $by->id,
            'expires_at' => now()->addHours(config('gym.registration_link_hours')),
        ]);
    }

    /** pending | used | revoked | expired */
    public function status(): string
    {
        return match (true) {
            $this->used_at !== null => 'used',
            $this->revoked_at !== null => 'revoked',
            $this->expires_at->isPast() => 'expired',
            default => 'pending',
        };
    }

    public function isUsable(): bool
    {
        return $this->status() === 'pending';
    }

    public function url(): string
    {
        return route('join', $this->token);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function member()
    {
        return $this->belongsTo(User::class, 'member_id');
    }
}
