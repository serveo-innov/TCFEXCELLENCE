<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class Coach extends Model
{
    use HasFactory;
    use HasUuid;

    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'bio',
        'expertise',
    ];

    // Relation : un coach appartient à un utilisateur
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relation : un coach a plusieurs corrections
    public function corrections()
    {
        return $this->hasMany(Correction::class, 'coach_id', 'user_id');
    }

    // Relation : un coach a plusieurs messages
    public function coachMessages()
    {
        return $this->hasMany(CoachMessage::class, 'coach_id', 'user_id');
    }

    // Relation : un coach a plusieurs rendez-vous
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'coach_id', 'user_id');
    }

    // Relation : un coach a plusieurs groupes
    public function groups()
    {
        return $this->hasMany(Group::class, 'coach_id', 'user_id');
    }
}