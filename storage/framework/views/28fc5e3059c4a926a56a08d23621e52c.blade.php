@foreach (\App\Models\MembershipPlan::CYCLES as $value => $cycle)
<option value="{{ $value }}" data-days="{{ $cycle['days'] }}">{{ $cycle['label'] }}</option>
@endforeach