<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Project extends Model
{
        use HasFactory;

    // protected $table = 'service_vehicle';1

    protected $fillable = [
        'centre_id',
        'service_id',
        'date'
    ];

    protected $casts = [
        'date' => 'date:d/m/Y',
    ];

    // protected function serializeDate(\DateTimeInterface $date)
    // {
    //     return $date->format('d/m/Y');
    // }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function centre()
    {
        return $this->belongsTo(Centre::class);
    }

    public function vehicles()
    {
        return $this->belongsToMany(Vehicle::class, 'project_vehicles', 'project_id', 'vehicle_id')
            ->withPivot('commentary', 'user_id') // Incluye el campo commentary de la tabla pivote    
            ->with(['type', 'project'])
            ->withTimestamps();
    }


}
