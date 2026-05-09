<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class Progress extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'learner_id',
        'competence_id',
        'score',
        'level',
    ];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    // Relation : un progress appartient à un apprenant
    public function learner()
    {
        return $this->belongsTo(Learner::class, 'learner_id', 'user_id');
    }

    // Relation : un progress appartient à une compétence
    public function competence()
    {
        return $this->belongsTo(Competence::class, 'competence_id');
    }
}