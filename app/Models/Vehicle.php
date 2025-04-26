<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'eco',
        'centre_id',
        'vehicle_type_id',
    ];

    public function centre()
    {
        return $this->belongsTo(Centre::class);
    }

    // public function type()
    // {
    //     return $this->belongsTo(VehicleType::class);
    // }

    public function type()
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }

    public function project()
    {
        return $this->belongsToMany(Project::class, 'project_vehicles', 'vehicle_id', 'project_id');
            // ->withPivot(['commentary']);
            // ->withTimestamps();
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_vehicles', 'vehicle_id', 'project_id');
            // ->withPivot(['commentary']);
            // ->withTimestamps();
    }

    public function projectUser()
    {
        return $this->hasOneThrough(
            User::class,
            ProjectVehicle::class,
            'vehicle_id', // Foreign key en la tabla pivote (project_vehicles)
            'id',         // Foreign key en la tabla users
            'id',         // Local key en la tabla vehicles
            'user_id',     // Local key en la tabla pivote
        );
    }
}
