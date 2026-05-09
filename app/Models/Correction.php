<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Correction extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'submission_id',
        'coach_id',
        'corrected_text',
        'audio_feedback_url',
        'score',
        'feedback',
        'is_ai_assisted',
        'ai_raw_result',
    ];

    protected $casts = [
        'score'          => 'decimal:2',
        'is_ai_assisted' => 'boolean',
        'ai_raw_result'  => 'array',
    ];

    // Relation : une correction appartient à une soumission
    public function submission()
    {
        return $this->belongsTo(Submission::class, 'submission_id');
    }

    // Relation : une correction appartient à un coach
    public function coach()
    {
        return $this->belongsTo(Coach::class, 'coach_id', 'user_id');
    }
}