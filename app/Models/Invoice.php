<?php

namespace App\Models;

use App\Models\InvoiceVehicle;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'centre_id', 
        'date', 
        'total', 
        'comments', 
        'path', 
        'invoice_number', 
        'completed', 
        'concept', 
        'quantity', 
        'price', 
        'internal_commentary', 
        'services', 
        'is_budget', 
        'responsible_id',
        'billing_path', 
        'complement_path', 
        'billing_xml_path', 
        'complement_xml_path'
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

    public function projectVehicles()
    {
        return $this->hasMany(ProjectVehicle::class, 'invoice_id');
    }

    public function rows()
    {
        return $this->hasMany(InvoiceRow::class);
    }

    public function billing()
    {
        return $this->belongsTo(Billing::class, 'billing_id');
    }

    public function complement()
    {
        return $this->belongsTo(Billing::class, 'complement_id');
    }

}
