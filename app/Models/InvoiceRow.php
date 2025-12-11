<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceRow extends Model
{
    protected $fillable = ['invoice_id', 'concept', 'quantity', 'price', 'total'];

    public function setPriceAttribute($value)
    {
        $this->attributes['price'] = $value;
        $this->attributes['total'] = ($this->quantity ?? 0) * $value;
    }

    public function setQuantityAttribute($value)
    {
        $this->attributes['quantity'] = $value;
        $this->attributes['total'] = ($this->price ?? 0) * $value;
    }
}

