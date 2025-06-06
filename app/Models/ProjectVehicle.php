<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectVehicle extends Model
{
    protected $table = 'project_vehicles'; // Especifica la tabla pivote

    protected $fillable = [
        'project_id',
        'vehicle_id',
        'user_id',
        'commentary',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
