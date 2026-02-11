<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceBilling extends Model
{
    protected $fillable = [
        'invoice_id',
        'billing_id',
        'pdf_path',
        'xml_path',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function billing()
    {
        return $this->belongsTo(Billing::class);
    }
}
