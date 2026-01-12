<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Centre extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'responsible',
        'customer_id',
    ];

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'services_centres')
            ->withTimestamps();
    }

    public function responsibles()
    {
        return $this->belongsToMany(Responsible::class, 'centre_responsible');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
