<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Submission extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'learner_id',
        'exercise_id',
        'type',
        'content_text',
        'audio_url',
        'submitted_at',
        'status',
        'score',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'score'        => 'decimal:2',
    ];

    // Relation : une soumission appartient à un apprenant
    public function learner()
    {
        return $this->belongsTo(Learner::class, 'learner_id', 'user_id');
    }

    // Relation : une soumission appartient à un exercice
    public function exercise()
    {
        return $this->belongsTo(Exercise::class, 'exercise_id');
    }

    // Relation : une soumission a une correction
    public function correction()
    {
        return $this->hasOne(Correction::class, 'submission_id');
    }

    // Relation : une soumission a plusieurs logs IA
    public function aiUsageLogs()
    {
        return $this->hasMany(AiUsageLog::class, 'submission_id');
    }
}