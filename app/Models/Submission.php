<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'exercise_id',
        'type',
        'content_url',
        'status',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    // Relation : une soumission appartient à un utilisateur
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relation : une soumission appartient à un exercice
    public function exercise()
    {
        return $this->belongsTo(Exercise::class);
    }

    // Relation : une soumission a une correction
    public function correction()
    {
        return $this->hasOne(Correction::class);
    }
}