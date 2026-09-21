<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Admin-only management of users of any role (members, trainers, receptionists, admins).
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return response()->json($query->latest()->paginate($request->get('per_page', 20)));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,trainer,member,receptionist',
            'specialization' => 'nullable|string',
            'bio' => 'nullable|string',
            'hourly_rate' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
        ]);

        if ($data['role'] === 'trainer') {
            $user->trainerProfile()->create([
                'specialization' => $data['specialization'] ?? null,
                'bio' => $data['bio'] ?? null,
                'hourly_rate' => $data['hourly_rate'] ?? null,
            ]);
        }

        return response()->json($user->load('trainerProfile'), 201);
    }

    public function show(User $user)
    {
        return response()->json($user->load('trainerProfile', 'memberPlans.plan'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'gender' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'emergency_contact_name' => 'nullable|string',
            'emergency_contact_phone' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $user->update($data);

        return response()->json($user);
    }

    public function destroy(User $user)
    {
        // Attendance and payslips are records the gym must keep; deactivate the account instead.
        if (\App\Models\StaffAttendance::where('user_id', $user->id)->exists() || \App\Models\Payslip::where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'This person has attendance or payroll records, so the account cannot be deleted. Deactivate it instead.'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }
}
