<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessProfile extends Model
{

        protected $table = 'business_profile';
    
        // protected $fillable = [
        //     'business_name',
        //     'legal_name',
        //     'rfc',
        //     'tax_regime',
        //     'address',
        //     'phone',
        //     'email',
        //     'logo_path',
        //     'invoice_footer',
        //     'currency',
        // ];
    
        // public $timestamps = true;
    
        // /**
        // * Obtiene el perfil de negocio actual o lo crea si no existe.
        // */

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }
}
