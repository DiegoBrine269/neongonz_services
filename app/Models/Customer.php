<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'legal_name',
        'email',
        'tax_id',
        'tax_system',
        'address',
    ];
}
