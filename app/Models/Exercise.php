<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class Exercise extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'title',
        'description',
        'competence_id',
        'type',
        'level',
        'content',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relation : un exercice appartient à une compétence
    public function competence()
    {
        return $this->belongsTo(Competence::class, 'competence_id');
    }

    // Relation : un exercice a plusieurs questions QCM
    public function qcmQuestions()
    {
        return $this->hasMany(QcmQuestion::class, 'exercise_id');
    }

    // Relation : un exercice a plusieurs soumissions
    public function submissions()
    {
        return $this->hasMany(Submission::class, 'exercise_id');
    }
}