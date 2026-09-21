<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Body, fitness and health details for a member (one row per member). */
class MemberProfile extends Model
{
    public const GOALS = [
        'weight_loss' => 'Weight loss',
        'muscle_gain' => 'Muscle gain',
        'strength' => 'Strength',
        'endurance' => 'Endurance / cardio',
        'flexibility' => 'Flexibility / mobility',
        'general_fitness' => 'General fitness',
        'rehab' => 'Rehab / recovery',
        'sports' => 'Sports performance',
    ];

    public const LEVELS = [
        'beginner' => 'Beginner',
        'intermediate' => 'Intermediate',
        'advanced' => 'Advanced',
    ];

    public const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    public const TIMINGS = [
        'morning' => 'Morning',
        'afternoon' => 'Afternoon',
        'evening' => 'Evening',
        'flexible' => 'Flexible',
    ];

    public const SOURCES = [
        'walk_in' => 'Walk-in',
        'friend' => 'Friend / referral',
        'social_media' => 'Social media',
        'website' => 'Website',
        'advertisement' => 'Advertisement',
        'other' => 'Other',
    ];

    /** Everything a member's profile stores (the users-table fields are handled by the controller). */
    public const FIELDS = [
        'height_cm', 'weight_kg', 'fitness_goal', 'fitness_level', 'blood_group', 'medical_conditions',
        'emergency_contact_relation', 'occupation', 'preferred_timing', 'referral_source', 'notes',
    ];

    protected $fillable = [
        'user_id', 'height_cm', 'weight_kg', 'fitness_goal', 'fitness_level', 'blood_group', 'medical_conditions',
        'emergency_contact_relation', 'occupation', 'preferred_timing', 'referral_source', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'height_cm' => 'float',
            'weight_kg' => 'float',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Body mass index to one decimal, or null until both height and weight are known. */
    public function bmi(): ?float
    {
        if (! $this->height_cm || ! $this->weight_kg) {
            return null;
        }

        return round($this->weight_kg / (($this->height_cm / 100) ** 2), 1);
    }

    public function bmiCategory(): ?string
    {
        $bmi = $this->bmi();

        return match (true) {
            $bmi === null => null,
            $bmi < 18.5 => 'underweight',
            $bmi < 25 => 'normal',
            $bmi < 30 => 'overweight',
            default => 'obese',
        };
    }
}
