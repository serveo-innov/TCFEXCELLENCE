<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'avatar_url',
        'role',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // Relations
    public function admin()
    {
        return $this->hasOne(Admin::class, 'user_id');
    }

    public function coach()
    {
        return $this->hasOne(Coach::class, 'user_id');
    }

    public function learner()
    {
        return $this->hasOne(Learner::class, 'user_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class, 'user_id');
    }

    public function passwordResets()
    {
        return $this->hasMany(PasswordReset::class, 'user_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }
}