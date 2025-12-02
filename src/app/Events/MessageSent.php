<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Данные сообщения, которые уйдут на фронт.
     */
    public array $message;

    /**
     * @param \App\Models\Message $message
     */
    public function __construct(Message $message)
    {
        // на всякий случай подгружаем автора
        $message->loadMissing('user');

        $this->message = [
            'id'         => $message->id,
            'chat_id'    => $message->chat_id,
            'user_id'    => $message->user_id,
            'body'       => $message->body,
            'created_at' => $message->created_at,
            'updated_at' => $message->updated_at,
            'user'       => $message->user ? [
                'id'          => $message->user->id,
                'name'        => $message->user->name,
                'email'       => $message->user->email,
                'nickname'    => $message->user->nickname,
                'avatar_path' => $message->user->avatar_path,
            ] : null,
        ];
    }

    /**
     * Канал: private-chat.{chat_id}
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('private-chat.' . $this->message['chat_id']),
        ];
    }

    /**
     * Имя события для Echo:
     * Echo.private(...).listen('MessageSent', ...)
     */
    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    /**
     * Payload в Echo: e => e.id, e.body, e.user и т.д.
     */
    public function broadcastWith(): array
    {
        return $this->message;
    }
}
