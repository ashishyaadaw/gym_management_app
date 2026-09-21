<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentMaintenanceLog extends Model
{
    protected $fillable = [
        'equipment_id', 'logged_by', 'maintenance_date', 'description', 'cost',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_date' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    public function loggedBy()
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
