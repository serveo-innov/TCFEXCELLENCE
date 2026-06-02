<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Message extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'group_id',
        'sender_id',
        'content',
        'message_type',
        'file_url',
        'reply_to_id',
        'is_pinned',
        'is_hidden',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_hidden' => 'boolean',
    ];

    // Relation : un message appartient à un groupe
    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    // Relation : un message appartient à un expéditeur
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // Relation : un message peut être une réponse à un autre
    public function replyTo()
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    // Relation : un message a plusieurs réponses
    public function replies()
    {
        return $this->hasMany(Message::class, 'reply_to_id');
    }

    // Relation : un message a plusieurs réactions
    public function reactions()
    {
        return $this->hasMany(MessageReaction::class, 'message_id');
    }

    // Relation : un message a plusieurs pièces jointes
    public function attachments()
    {
        return $this->hasMany(MessageAttachment::class, 'message_id');
    }
}