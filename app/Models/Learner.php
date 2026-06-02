<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Learner extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'registration_type',
        'country',
        'target_exam_date',
        'estimated_level',
        'global_score',
        'is_expert_candidate',
        'last_active_at',
    ];

    protected $casts = [
        'target_exam_date'    => 'date',
        'global_score'        => 'decimal:2',
        'is_expert_candidate' => 'boolean',
        'last_active_at'      => 'datetime',
    ];

    // Relation : un apprenant appartient à un utilisateur
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relation : un apprenant a plusieurs progress
    public function progress()
    {
        return $this->hasMany(Progress::class, 'learner_id', 'user_id');
    }

    // Relation : un apprenant a plusieurs soumissions
    public function submissions()
    {
        return $this->hasMany(Submission::class, 'learner_id', 'user_id');
    }

    // Relation : un apprenant a plusieurs messages coach
    public function coachMessages()
    {
        return $this->hasMany(CoachMessage::class, 'learner_id', 'user_id');
    }

    // Relation : un apprenant a plusieurs rendez-vous
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'learner_id', 'user_id');
    }

    // Relation : un apprenant est membre de plusieurs groupes
    public function groupMembers()
    {
        return $this->hasMany(GroupMember::class, 'learner_id', 'user_id');
    }
}