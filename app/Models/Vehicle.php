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

    protected $hidden = ['pivot'];

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
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_vehicles', 'vehicle_id', 'project_id')
            ->withTimestamps();
            // ->withPivot(['commentary']);
    }

    public function projectVehicle()
    {
        return $this->hasOne(ProjectVehicle::class)->latestOfMany(); // si solo quieres el más reciente
    }
    
    public function projectUser()
    {
        return $this->projectVehicle->user ?? null;
    }


    public function invoices()
    {
        return $this->belongsToMany(Invoice::class, 'invoice_vehicles', 'vehicle_id', 'invoice_id')
                    ->withPivot('project_id') // Incluye campos adicionales de la tabla pivote si es necesario
                    ->withTimestamps(); // Si la tabla pivote tiene timestamps
    }
}
