<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements PasskeyUser
{
    use HasFactory, Notifiable, HasRoles, PasskeyAuthenticatable;

    protected $guard_name = 'web';

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'username',
        'group_id',
        'status',
        'balance',
        'password',
        'google_id',
        'two_factor_enabled',
        'two_factor_method',
        'two_factor_secret',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    public function group()
    {
        return $this->belongsTo(\App\Models\Group::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
