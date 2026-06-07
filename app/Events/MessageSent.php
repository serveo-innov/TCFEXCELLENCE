<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('group.' . $this->message->group_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id'           => $this->message->id,
            'group_id'     => $this->message->group_id,
            'sender_id'    => $this->message->sender_id,
            'sender_name'  => $this->message->sender->name,
            'sender_role'  => $this->message->sender->role,
            'content'      => $this->message->content,
            'message_type' => $this->message->message_type,
            'file_url'     => $this->message->file_url,
            'reply_to_id'  => $this->message->reply_to_id,
            'is_pinned'    => $this->message->is_pinned,
            'created_at'   => $this->message->created_at->toIso8601String(),
        ];
    }
}
