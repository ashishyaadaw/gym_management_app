<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassSchedule;
use Illuminate\Http\Request;

class ClassScheduleController extends Controller
{
    public function index(Request $request)
    {
        $query = ClassSchedule::with(['gymClass', 'trainer'])->withCount('confirmedBookings');

        if ($request->filled('from')) {
            $query->where('start_time', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('start_time', '<=', $request->to);
        }
        if ($request->filled('trainer_id')) {
            $query->where('trainer_id', $request->trainer_id);
        }
        if ($request->filled('gym_class_id')) {
            $query->where('gym_class_id', $request->gym_class_id);
        }
        if ($request->user()->isTrainer() && $request->boolean('mine')) {
            $query->where('trainer_id', $request->user()->id);
        }

        return response()->json($query->orderBy('start_time')->paginate($request->get('per_page', 30)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'gym_class_id' => 'required|exists:gym_classes,id',
            'trainer_id' => 'required|exists:users,id',
            'room' => 'nullable|string',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'capacity_override' => 'nullable|integer|min:1',
        ]);

        return response()->json(ClassSchedule::create($data)->load('gymClass', 'trainer'), 201);
    }

    public function show(ClassSchedule $classSchedule)
    {
        return response()->json($classSchedule->load('gymClass', 'trainer', 'confirmedBookings.user'));
    }

    public function update(Request $request, ClassSchedule $classSchedule)
    {
        $data = $request->validate([
            'room' => 'nullable|string',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date|after:start_time',
            'capacity_override' => 'nullable|integer|min:1',
            'status' => 'sometimes|in:scheduled,cancelled,completed',
            'cancellation_reason' => 'nullable|string',
        ]);

        $classSchedule->update($data);

        return response()->json($classSchedule);
    }

    public function destroy(ClassSchedule $classSchedule)
    {
        $classSchedule->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Class session cancelled.']);
    }
}
