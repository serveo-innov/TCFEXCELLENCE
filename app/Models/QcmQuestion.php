<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class QcmQuestion extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'exercise_id',
        'question',
        'explanation',
        'points',
    ];

    protected $casts = [
        'points' => 'decimal:2',
    ];

    // Relation : une question appartient à un exercice
    public function exercise()
    {
        return $this->belongsTo(Exercise::class, 'exercise_id');
    }

    // Relation : une question a plusieurs options
    public function options()
    {
        return $this->hasMany(QcmOption::class, 'qcm_question_id');
    }
}