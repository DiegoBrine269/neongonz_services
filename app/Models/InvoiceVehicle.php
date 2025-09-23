<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class InvoiceVehicle extends Pivot
{
    protected $table = 'invoice_vehicles';

    protected $fillable = ['vehicle_id', 'project_id', 'commentary'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
