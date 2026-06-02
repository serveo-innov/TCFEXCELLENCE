<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CoachMessage extends Model
{
    use HasFactory, HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'learner_id',
        'coach_id',
        'message',
        'is_pinned',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
    ];

    // Relation : un message appartient à un apprenant
    public function learner()
    {
        return $this->belongsTo(Learner::class, 'learner_id', 'user_id');
    }

    // Relation : un message appartient à un coach
    public function coach()
    {
        return $this->belongsTo(Coach::class, 'coach_id', 'user_id');
    }
}