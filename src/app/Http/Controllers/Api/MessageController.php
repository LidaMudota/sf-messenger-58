<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageDeleted;
use App\Events\MessageEdited;
use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\Request;
use Throwable;

class MessageController extends Controller
{
    // пагинация сообщений по чату
    public function index(Request $r, Chat $chat)
    {
        abort_unless($chat->users()->whereKey($r->user()->id)->exists(), 403);

        return Message::query()
            ->where('chat_id', $chat->id)
            ->with('user')
            ->orderByDesc('id')
            ->paginate(20);
    }

    // отправка сообщения + broadcast
    public function store(Request $r, Chat $chat)
    {
        abort_unless($chat->users()->whereKey($r->user()->id)->exists(), 403);

        $data = $r->validate([
            'body' => ['required', 'string'],
        ]);

        $msg = Message::create([
            'chat_id' => $chat->id,
            'user_id' => $r->user()->id,
            'body'    => $data['body'],
        ]);

        // сразу подгружаем автора/чат
        $msg->load(['user', 'chat']);

        // рассылаем другим участникам чата: private-chat.{chat_id}
        try {
            broadcast(new MessageSent($msg))->toOthers();
        } catch (Throwable $e) {
            // если что-то не так с Reverb/WS — пишем в лог, но не роняем запрос
            report($e);
        }

        // фронт ждёт объект сообщения
        return response()->json($msg, 201);
    }

    // редактирование сообщения (только автор) + broadcast
    public function update(Request $r, Message $message)
    {
        abort_unless($message->user_id === $r->user()->id, 403);

        $data = $r->validate([
            'body' => ['required', 'string'],
        ]);

        $message->update([
            'body'      => $data['body'],
            'edited_at' => now(),
        ]);

        $message->load(['user', 'chat']);

        try {
            broadcast(new MessageEdited($message->chat_id, $message))->toOthers();
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json($message, 200);
    }

    // удаление (только автор) + broadcast
    public function destroy(Request $r, Message $message)
    {
        abort_unless($message->user_id === $r->user()->id, 403);

        $chatId    = $message->chat_id;
        $messageId = $message->id;

        $message->delete();

        try {
            broadcast(new MessageDeleted($chatId, $messageId))->toOthers();
        } catch (Throwable $e) {
            report($e);
        }

        return response()->noContent();
    }

    // пересылка сообщения в другой чат
    public function forward(Request $r, Message $message)
    {
        $data = $r->validate([
            'target_chat_id' => ['required', 'integer', 'exists:chats,id'],
        ]);

        $targetChat = Chat::findOrFail($data['target_chat_id']);

        abort_unless(
            $targetChat->users()->whereKey($r->user()->id)->exists(),
            403
        );

        $copy = Message::create([
            'chat_id' => $targetChat->id,
            'user_id' => $r->user()->id,
            'body'    => $message->body,
        ]);

        $copy->load(['user', 'chat']);

        try {
            broadcast(new MessageSent($targetChat->id, $copy))->toOthers();
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json($copy, 201);
    }
}
