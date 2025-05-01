<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'last_name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    public function vehicles()
    {
        return $this->belongsToMany(Vehicle::class, 'project_vehicles', 'user_id', 'vehicle_id');
            // ->withPivot('commentary'); // Incluye campos adicionales de la tabla pivote si es necesario
    }

    public function sendPasswordResetNotification($token, $url = null)
    {
        if (!$url) {
            $url = url("/reset-password?token=$token&email=" . urlencode($this->email));
        }

        $this->notify(new ResetPasswordNotification($token, $url));
    }
}
