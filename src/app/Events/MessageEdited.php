<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageEdited implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $chatId;
    public array $message;

    /**
     * Create a new event instance.
     */
    public function __construct(Message $message)
    {
        // подгружаем автора, чтобы на фронте сразу был msg.user
        $message->load('user');

        $this->chatId = $message->chat_id;

        $this->message = [
            'id'         => $message->id,
            'chat_id'    => $message->chat_id,
            'user_id'    => $message->user_id,
            'body'       => $message->body,
            'created_at' => $message->created_at,
            'updated_at' => $message->updated_at,
            'edited_at'  => $message->edited_at,
            'user'       => $message->user
                ? [
                    'id'          => $message->user->id,
                    'name'        => $message->user->name,
                    'nickname'    => $message->user->nickname,
                    'avatar_path' => $message->user->avatar_path,
                ]
                : null,
        ];
    }

    /**
     * Канал: private-chat.{chatId}
     *
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('private-chat.' . $this->chatId),
        ];
    }

    /**
     * Короткое имя события для Echo.listen('MessageEdited', ...)
     */
    public function broadcastAs(): string
    {
        return 'MessageEdited';
    }

    public function broadcastWith(): array
    {
        return $this->message;
    }
}
