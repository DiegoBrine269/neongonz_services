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
        'complement_xml_path',
        'is_custom',
        'oc',
        'f_receipt',
        'status',
        'validation_date',
    ];

    protected $casts = [
        'is_budget' => 'boolean',
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
        return $this->hasOneThrough(
                Billing::class,
                        InvoiceBilling::class,
                                'invoice_id',
                                        'id',
                                                'id',
                                                        'billing_id'
                                                            )
                                                                ->where('billings.type', 'factura')
                                                                    ->latest('billings.id');
                                                                    }

    public function billings()
    {
        return $this->belongsToMany(Billing::class, 'invoice_billings')->withTimestamps();
    }

    public function complements()
    {
        return $this->hasManyThrough(
            Billing::class,
            InvoiceBilling::class,
            'invoice_id',
            'id',
            'id',
            'billing_id'
        )->where('billings.type', 'complemento');
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

}
