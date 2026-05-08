<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetenceScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'competence',
        'score',
    ];

    protected $casts = [
        'score' => 'float',
    ];

    // Relation : un score appartient à un utilisateur
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}