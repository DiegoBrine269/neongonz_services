<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Facades\Storage;

class ProjectVehiclesPhoto extends Model
{
    //
    protected $fillable = ['project_vehicle_id', 'path'];
    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return url(Storage::temporaryUrl('projects/' . $this->path, now()->addMinutes(30)));
    }

    public function projectVehicle(): BelongsTo
    {
        return $this->belongsTo(ProjectVehicle::class);
    }

    public function vehicle(): HasOneThrough
    {
        return $this->hasOneThrough(
            Vehicle::class,
            ProjectVehicle::class,
            'id',                 // project_vehicles.id
            'id',                 // vehicles.id
            'project_vehicle_id', // project_vehicles_photos.project_vehicle_id
            'vehicle_id'          // project_vehicles.vehicle_id
        );
    }
}
