<?php

namespace App\Http\Controllers\Concerns;

use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/** The optional member details (contact, emergency contact, body, fitness, health) — shared by the desk and the join link. */
trait HandlesMemberDetails
{
    /** Validation for the details a member can have; every one is optional. */
    private function detailRules(): array
    {
        return [
            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date|before:today|after:1900-01-01',
            'address' => 'nullable|string|max:500',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_relation' => 'nullable|string|max:50',
            'height_cm' => 'nullable|numeric|between:50,260',
            'weight_kg' => 'nullable|numeric|between:20,400',
            'fitness_goal' => ['nullable', Rule::in(array_keys(MemberProfile::GOALS))],
            'fitness_level' => ['nullable', Rule::in(array_keys(MemberProfile::LEVELS))],
            'blood_group' => ['nullable', Rule::in(MemberProfile::BLOOD_GROUPS)],
            'preferred_timing' => ['nullable', Rule::in(array_keys(MemberProfile::TIMINGS))],
            'referral_source' => ['nullable', Rule::in(array_keys(MemberProfile::SOURCES))],
            'occupation' => 'nullable|string|max:255',
            'medical_conditions' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    /** Save validated details: the contact fields on the user, the body/fitness/health fields on their profile. */
    private function saveDetails(User $member, array $data): void
    {
        $member->update(Arr::only($data, [
            'name', 'phone', 'gender', 'date_of_birth', 'address', 'emergency_contact_name', 'emergency_contact_phone',
        ]));

        MemberProfile::updateOrCreate(['user_id' => $member->id], Arr::only($data, MemberProfile::FIELDS));
    }
}
