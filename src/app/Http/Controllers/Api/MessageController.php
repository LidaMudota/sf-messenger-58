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
    public function index(Request $r, Chat $chat)
    {
        abort_unless($chat->users()->whereKey($r->user()->id)->exists(), 403);

        return Message::query()
            ->where('chat_id', $chat->id)
            ->with('user')
            ->orderByDesc('id')
            ->paginate(20);
    }

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

        $msg->load(['user', 'chat']);

        try {
            broadcast(new MessageSent($msg))->toOthers();
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json($msg, 201);
    }

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
            // БЫЛА ОШИБКА: передавался chat_id и Message
            broadcast(new MessageEdited($message))->toOthers();
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json($message, 200);
    }

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
            broadcast(new MessageSent($copy))->toOthers();
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json($copy, 201);
    }
}
