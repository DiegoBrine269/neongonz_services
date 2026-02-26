<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Billing extends Model
{
    //
    protected $fillable = [
        'uuid',
        'folio_number',
        'payment_method',
        'payment_form',
        'total',
        'type',
        'pdf_path',
        'xml_path',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
