<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Competence extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'description',
        'weight',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
    ];

    public function progress()
    {
        return $this->hasMany(Progress::class, 'competence_id');
    }

    public function exercises()
    {
        return $this->hasMany(Exercise::class, 'competence_id');
    }
}