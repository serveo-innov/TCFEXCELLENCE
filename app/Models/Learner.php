<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Learner extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'user_id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'user_id',
        'registration_type',
        'country',
        'target_exam_date',
        'estimated_level',
        'global_score',
        'is_expert_candidate',
        'last_active_at',
        'banner_hidden_until',
        'private_note',
    ];

    protected $casts = [
        'target_exam_date'    => 'date',
        'global_score'        => 'decimal:2',
        'is_expert_candidate' => 'boolean',
        'last_active_at'      => 'datetime',
        'banner_hidden_until' => 'datetime',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function progress()
    {
        return $this->hasMany(Progress::class, 'learner_id', 'user_id');
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class, 'learner_id', 'user_id');
    }

    public function coachMessages()
    {
        return $this->hasMany(CoachMessage::class, 'learner_id', 'user_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'learner_id', 'user_id');
    }

    public function groupMembers()
    {
        return $this->hasMany(GroupMember::class, 'learner_id', 'user_id');
    }
}
