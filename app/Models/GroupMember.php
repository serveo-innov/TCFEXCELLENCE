<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class GroupMember extends Model
{
    use HasFactory, HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'group_id',
        'learner_id',
        'role_in_group',
        'joined_at',
        'is_active',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // Relation : un membre appartient à un groupe
    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    // Relation : un membre appartient à un apprenant
    public function learner()
    {
        return $this->belongsTo(Learner::class, 'learner_id', 'user_id');
    }
}