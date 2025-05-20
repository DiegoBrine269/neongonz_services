<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceVehicle extends Model
{
    protected $fillable = ['vehicle_id', 'project_id', 'commentary'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }
}
