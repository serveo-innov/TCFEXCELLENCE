<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearnerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'exam_target_date',
        'coach_message',
        'coach_note_private',
        'banner_dismissed_until',
        'last_activity_at',
    ];

    protected $casts = [
        'exam_target_date'       => 'date',
        'banner_dismissed_until' => 'datetime',
        'last_activity_at'       => 'datetime',
    ];

    // Relation : un profil appartient à un utilisateur
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}