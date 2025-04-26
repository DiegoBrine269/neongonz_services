<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleType extends Model
{

    protected $table = 'vehicles_types';

    protected $fillable = [
        'id',
        'type',
    ];

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }
}
