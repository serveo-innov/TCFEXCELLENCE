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
    use HasApiTokens, HasFactory, Notifiable, HasRoles, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'avatar_url',
        'role',        // ← ajouté
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

    // Relations profils
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

    // tcfNotifications — renommé pour éviter conflit avec Notifiable
    public function tcfNotifications()
    {
        return $this->hasMany(TcfNotification::class, 'user_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }
}