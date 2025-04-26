<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function vehicleTypes()
    {
        return $this->belongsToMany(VehicleType::class, 'service_vehicle_type', 'service_id', 'vehicle_type_id')
                    ->withPivot(['price']); // Replace 'column1', 'column2' with actual column names from the pivot table
    }
}
