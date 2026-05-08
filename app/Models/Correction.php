<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Correction extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'ai_raw_response',
        'coach_feedback',
        'validated_by',
        'validated_at',
        'ai_latency_ms',
    ];

    protected $casts = [
        'ai_raw_response' => 'array',
        'validated_at'    => 'datetime',
    ];

    // Relation : une correction appartient à une soumission
    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    // Relation : une correction est validée par un admin
    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}