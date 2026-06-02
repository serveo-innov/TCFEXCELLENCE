<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TcfNotification extends Model  // ← renommé
{
    use HasFactory, HasUuids;

    protected $table = 'tcf_notifications'; // ← table renommée

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'content',
        'data',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'data'    => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}