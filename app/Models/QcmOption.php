<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class QcmOption extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'qcm_question_id',
        'content',
        'is_correct',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    // Relation : une option appartient à une question QCM
    public function question()
    {
        return $this->belongsTo(QcmQuestion::class, 'qcm_question_id');
    }
}