<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Billing extends Model
{
    //
    protected $fillable = [
        'uuid',
        'payment_method',
        'payment_form',
        'type',
        'pdf_path',
        'xml_path',
    ];
}
