<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Obtener el identificador único que se guardará en el claim 'sub' del JWT.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Retornar un array asociativo con claims personalizados para inyectar en el payload del JWT.
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'role' => 'customer',
            'email' => $this->email,
        ];
    }

    /**
     * Relación con las órdenes del e-commerce.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}