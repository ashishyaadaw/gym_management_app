<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use Illuminate\Http\Request;

class EquipmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Equipment::query();
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('category')) $query->where('category', $request->category);
        if ($request->filled('search')) $query->where('name', 'like', '%' . $request->search . '%');

        return response()->json($query->orderBy('name')->paginate($request->get('per_page', 30)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string',
            'serial_number' => 'nullable|string|unique:equipment,serial_number',
            'manufacturer' => 'nullable|string',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'location' => 'nullable|string',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        return response()->json(Equipment::create($data), 201);
    }

    public function show(Equipment $equipment)
    {
        return response()->json($equipment->load('maintenanceLogs.loggedBy'));
    }

    public function update(Request $request, Equipment $equipment)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category' => 'nullable|string',
            'status' => 'sometimes|in:available,in_use,under_maintenance,retired',
            'location' => 'nullable|string',
            'quantity' => 'sometimes|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $equipment->update($data);

        return response()->json($equipment);
    }

    public function destroy(Equipment $equipment)
    {
        $equipment->update(['status' => 'retired']);

        return response()->json(['message' => 'Equipment retired.']);
    }

    public function logMaintenance(Request $request, Equipment $equipment)
    {
        $data = $request->validate([
            'maintenance_date' => 'required|date',
            'description' => 'required|string',
            'cost' => 'nullable|numeric|min:0',
        ]);

        $log = $equipment->maintenanceLogs()->create([
            ...$data,
            'logged_by' => $request->user()->id,
        ]);

        $equipment->update([
            'last_maintenance_date' => $data['maintenance_date'],
            'status' => 'available',
        ]);

        return response()->json($log, 201);
    }
}
