<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = ['centre_id', 'date', 'total', 'comments', 'path', 'invoice_number'];

    protected $casts = [
        'date' => 'date:d/m/Y',
    ];

    public function invoiceVehicles() {
        return $this->hasMany(InvoiceVehicle::class);
    }

    public function centre(){
        return $this->belongsTo(Centre::class);
    }

    public function vehicles()
    {
        return $this->belongsToMany(Vehicle::class, 'invoice_vehicles', 'invoice_id', 'vehicle_id')
                    ->withPivot('project_id') // Incluye campos adicionales de la tabla pivote si es necesario
                    ->withTimestamps(); // Si la tabla pivote tiene timestamps
    }
}
