<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class PlatformOperator extends Authenticatable
{
    use Notifiable;

    protected $connection = 'central';

    protected $fillable = ['name', 'email', 'password', 'is_active', 'mfa_enabled'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean', 'mfa_enabled' => 'boolean'];
    }

}
