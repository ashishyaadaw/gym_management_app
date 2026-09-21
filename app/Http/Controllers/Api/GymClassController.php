<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GymClass;
use Illuminate\Http\Request;

class GymClassController extends Controller
{
    public function index(Request $request)
    {
        $query = GymClass::query();
        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'duration_minutes' => 'required|integer|min:1',
            'capacity' => 'required|integer|min:1',
        ]);

        return response()->json(GymClass::create($data), 201);
    }

    public function show(GymClass $gymClass)
    {
        return response()->json($gymClass->load(['schedules' => fn ($q) => $q->where('start_time', '>=', now())->orderBy('start_time')]));
    }

    public function update(Request $request, GymClass $gymClass)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'duration_minutes' => 'sometimes|integer|min:1',
            'capacity' => 'sometimes|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);

        $gymClass->update($data);

        return response()->json($gymClass);
    }

    public function destroy(GymClass $gymClass)
    {
        $gymClass->update(['is_active' => false]);

        return response()->json(['message' => 'Class deactivated.']);
    }
}
