<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Responsible extends Model
{

    protected $fillable = ['name', 'email'];

    public function centres()
    {
        return $this->belongsToMany(Centre::class, 'centre_responsible');
    }
}
