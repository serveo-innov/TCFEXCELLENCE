<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class Competence extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'code',
        'name',
        'description',
        'weight',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
    ];

    // Relation : une compétence a plusieurs progress
    public function progress()
    {
        return $this->hasMany(Progress::class, 'competence_id');
    }

    // Relation : une compétence a plusieurs exercices
    public function exercises()
    {
        return $this->hasMany(Exercise::class, 'competence_id');
    }
}