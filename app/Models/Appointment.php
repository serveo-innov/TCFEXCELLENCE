<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'learner_id',
        'coach_id',
        'title',
        'description',
        'start_at',
        'end_at',
        'mode',
        'meeting_link',
        'status',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at'   => 'datetime',
    ];

    // Relation : un rendez-vous appartient à un apprenant
    public function learner()
    {
        return $this->belongsTo(Learner::class, 'learner_id', 'user_id');
    }

    // Relation : un rendez-vous appartient à un coach
    public function coach()
    {
        return $this->belongsTo(Coach::class, 'coach_id', 'user_id');
    }
}