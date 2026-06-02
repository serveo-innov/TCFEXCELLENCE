<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class MessageReaction extends Model
{
    use HasFactory, HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'message_id',
        'user_id',
        'emoji',
    ];

    // Relation : une réaction appartient à un message
    public function message()
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    // Relation : une réaction appartient à un utilisateur
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}