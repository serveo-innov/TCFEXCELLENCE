<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Progress extends Model
{
    use HasFactory, HasUuids;

    // $timestamps = true par défaut — on le retire car on a ajouté timestamps() en migration
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'learner_id',
        'competence_id',
        'score',
        'level',
    ];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    public function learner()
    {
        return $this->belongsTo(Learner::class, 'learner_id', 'user_id');
    }

    public function competence()
    {
        return $this->belongsTo(Competence::class, 'competence_id');
    }
}