<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserPresenceUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $groupId,
        public string $userId,
        public string $userName,
        public string $status // 'online' | 'offline'
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('group.' . $this->groupId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.presence';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id'  => $this->userId,
            'name'     => $this->userName,
            'status'   => $this->status,
        ];
    }
}
