<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

// app/Models/ServiceVehicleType.php
class ServiceVehicleType extends Model
{
    protected $table = 'service_vehicle_type';

    protected $fillable = [
        'service_id',
        'vehicle_type_id',
        'price',
        'centre_id',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function centre(): BelongsTo
    {
        return $this->belongsTo(Centre::class);
    }
}