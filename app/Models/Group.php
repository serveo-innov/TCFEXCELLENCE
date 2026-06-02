<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Group extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'type',
        'competence',
        'coach_id',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relation : un groupe appartient à un coach
    public function coach()
    {
        return $this->belongsTo(Coach::class, 'coach_id', 'user_id');
    }

    // Relation : un groupe a plusieurs membres
    public function members()
    {
        return $this->hasMany(GroupMember::class, 'group_id');
    }

    // Relation : un groupe a plusieurs messages
    public function messages()
    {
        return $this->hasMany(Message::class, 'group_id');
    }
}