<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $chatId;
    public int $messageId;

    /**
     * @param int $chatId
     * @param int $messageId
     */
    public function __construct(int $chatId, int $messageId)
    {
        $this->chatId    = $chatId;
        $this->messageId = $messageId;
    }

    /**
     * Канал для трансляции: private-chat.{chatId}
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('private-chat.' . $this->chatId),
        ];
    }

    /**
     * Имя события для Echo: Echo.private(...).listen('MessageDeleted', ...)
     */
    public function broadcastAs(): string
    {
        return 'MessageDeleted';
    }

    /**
     * Payload для фронта
     */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'chat_id'    => $this->chatId,
        ];
    }
}
