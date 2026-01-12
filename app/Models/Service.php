<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Service extends Model
{
    use HasFactory;

    protected $casts = [
        'is_public' => 'boolean',
    ];

    protected $fillable = [
        'name',
        'is_public',
        'sat_unit_key',
        'sat_key_prod_serv',
    ];

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function vehicleTypes()
    {
        return $this->belongsToMany(VehicleType::class, 'service_vehicle_type', 'service_id', 'vehicle_type_id')
                    ->withPivot(['price', 'centre_id']);
    }
    
    public function resolveVehicleTypePrice(int $vehicleTypeId, ?int $centreId): ?string
    {
        static $cache = [];

        $key = $this->id.'|'.$vehicleTypeId.'|'.($centreId ?? 'null');

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        return $cache[$key] = DB::table('service_vehicle_type')
            ->where('service_id', $this->id)
            ->where('vehicle_type_id', $vehicleTypeId)
            ->where(function ($q) use ($centreId) {
                $q->whereNull('centre_id');
                if ($centreId !== null) {
                    $q->orWhere('centre_id', $centreId);
                }
            })
            // específico primero, general después
            ->orderByRaw('centre_id IS NULL')
            ->value('price');
    }
    
}
