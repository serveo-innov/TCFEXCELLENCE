<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'competence',
        'level',
        'title',
        'content',
        'created_by',
    ];

    // Relation : un exercice appartient à un admin
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Relation : un exercice a plusieurs soumissions
    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }
}