{{-- Member detail inputs, shared by the "Add member" / "Edit member" forms and the public join page ($public = true:
     worded for the member, no internal notes). Every field is optional. Put inside a .row.g-3 --}}
@php
    $public = $public ?? false;
    use App\Models\MemberProfile;
    $opts = fn (array $list) => collect($list)->map(fn ($label, $value) => '<option value="'.e($value).'">'.e($label).'</option>')->implode('');
@endphp

<div class="col-12"><div class="gf-eyebrow">Personal</div></div>
<div class="col-sm-6">
    <label class="form-label small fw-medium">Gender</label>
    <select name="gender" class="form-select">
        <option value="">—</option>
        <option value="male">Male</option>
        <option value="female">Female</option>
        <option value="other">Other</option>
    </select>
</div>
<div class="col-sm-6">
    <label class="form-label small fw-medium">Date of birth</label>
    <input name="date_of_birth" type="date" class="form-control" max="{{ today()->toDateString() }}">
</div>
<div class="col-12">
    <label class="form-label small fw-medium">Address</label>
    <textarea name="address" class="form-control" rows="2" maxlength="500" placeholder="House / street, area, city"></textarea>
</div>
<div class="col-sm-6">
    <label class="form-label small fw-medium">Occupation</label>
    <input name="occupation" class="form-control" maxlength="255" placeholder="e.g. Software engineer">
</div>
<div class="col-sm-6">
    <label class="form-label small fw-medium">How did {{ $public ? 'you' : 'they' }} hear about us?</label>
    <select name="referral_source" class="form-select"><option value="">—</option>{!! $opts(MemberProfile::SOURCES) !!}</select>
</div>

<div class="col-12"><div class="gf-eyebrow mt-2">Emergency contact</div></div>
<div class="col-sm-6">
    <label class="form-label small fw-medium">Name</label>
    <input name="emergency_contact_name" class="form-control" maxlength="255" placeholder="Sunita Sharma">
</div>
<div class="col-sm-6">
    <label class="form-label small fw-medium">Phone</label>
    <input name="emergency_contact_phone" class="form-control" inputmode="numeric" maxlength="20" placeholder="9876543211">
</div>
<div class="col-sm-6">
    <label class="form-label small fw-medium">Relation</label>
    <input name="emergency_contact_relation" class="form-control" maxlength="50" placeholder="e.g. Mother, Spouse">
</div>

<div class="col-12"><div class="gf-eyebrow mt-2">Fitness &amp; health</div></div>
<div class="col-sm-6">
    <label class="form-label small fw-medium">Height (cm)</label>
    <input name="height_cm" type="number" min="50" max="260" step="0.1" class="form-control" placeholder="172">
</div>
<div class="col-sm-6">
    <label class="form-label small fw-medium">Weight (kg)</label>
    <input name="weight_kg" type="number" min="20" max="400" step="0.1" class="form-control" placeholder="70">
</div>
<div class="col-sm-6">
    <label class="form-label small fw-medium">Fitness goal</label>
    <select name="fitness_goal" class="form-select"><option value="">—</option>{!! $opts(MemberProfile::GOALS) !!}</select>
</div>
<div class="col-sm-6">
    <label class="form-label small fw-medium">Experience level</label>
    <select name="fitness_level" class="form-select"><option value="">—</option>{!! $opts(MemberProfile::LEVELS) !!}</select>
</div>
<div class="col-sm-6">
    <label class="form-label small fw-medium">Preferred workout time</label>
    <select name="preferred_timing" class="form-select"><option value="">—</option>{!! $opts(MemberProfile::TIMINGS) !!}</select>
</div>
<div class="col-sm-6">
    <label class="form-label small fw-medium">Blood group</label>
    <select name="blood_group" class="form-select">
        <option value="">—</option>
        @foreach (MemberProfile::BLOOD_GROUPS as $group)
            <option value="{{ $group }}">{{ $group }}</option>
        @endforeach
    </select>
</div>
<div class="col-12">
    <label class="form-label small fw-medium">Medical conditions / injuries</label>
    <textarea name="medical_conditions" class="form-control" rows="2" maxlength="2000" placeholder="Anything a trainer should know: injuries, asthma, heart or blood-pressure issues, allergies, medication…"></textarea>
</div>
@unless ($public)
<div class="col-12">
    <label class="form-label small fw-medium">Notes</label>
    <textarea name="notes" class="form-control" rows="2" maxlength="2000" placeholder="Internal notes about this member"></textarea>
</div>
@endunless
