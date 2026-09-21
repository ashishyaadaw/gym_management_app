<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    protected $table = 'equipment';

    protected $fillable = [
        'name', 'category', 'serial_number', 'manufacturer', 'purchase_date',
        'purchase_cost', 'status', 'last_maintenance_date', 'next_maintenance_date',
        'location', 'quantity', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'last_maintenance_date' => 'date',
            'next_maintenance_date' => 'date',
            'purchase_cost' => 'decimal:2',
        ];
    }

    public function maintenanceLogs()
    {
        return $this->hasMany(EquipmentMaintenanceLog::class);
    }
}
