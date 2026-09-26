<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesMemberDetails;
use App\Models\RegistrationLink;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The public page behind a one-time registration link (see Api\RegistrationLinkController).
 * A new member fills in their own details and is registered without a plan; the desk adds the plan later.
 */
class JoinController extends Controller
{
    use HandlesMemberDetails;

    public function show(string $token)
    {
        $link = RegistrationLink::where('token', $token)->first();

        if (! $link || ! $link->isUsable()) {
            return response()->view('auth.join-closed', ['status' => $link?->status() ?? 'missing'], $link ? 410 : 404);
        }

        return view('auth.join', ['link' => $link]);
    }

    public function store(Request $request, string $token)
    {
        $closed = 'This registration link has already been used or has expired. Ask the front desk for a new one.';
        abort_unless(RegistrationLink::where('token', $token)->first()?->isUsable(), 410, $closed);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['required', 'regex:/^\d{10}$/', 'unique:users,phone'],
            'email' => 'nullable|required_with:password|email|unique:users,email',
            'password' => 'nullable|string|min:8|confirmed',
        ] + Arr::except($this->detailRules(), 'notes'), [
            'phone.regex' => 'Phone must be exactly 10 digits.',
            'phone.unique' => 'This phone number is already registered. Please contact the front desk.',
            'email.required_with' => 'Enter your email to go with the password — it is what you sign in with.',
        ]);

        // Lock the link so two submissions of the same form can't both register.
        $member = DB::transaction(function () use ($token, $data, $closed) {
            $link = RegistrationLink::where('token', $token)->lockForUpdate()->first();

            abort_if(! $link || ! $link->isUsable(), 410, $closed);

            $member = User::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                // Same as a walk-in: without an email, derive one to fill the required, unique column.
                'email' => $data['email'] ?? $data['phone'].User::DERIVED_EMAIL_DOMAIN,
                'password' => Hash::make($data['password'] ?? Str::random(16)),
                'role' => 'member',
            ]);

            $this->saveDetails($member, Arr::except($data, 'notes'));

            $link->update(['used_at' => now(), 'member_id' => $member->id]);

            return $member;
        });

        return response()->json([
            'name' => $member->name,
            'can_sign_in' => ! empty($data['password']),
        ], 201);
    }
}
