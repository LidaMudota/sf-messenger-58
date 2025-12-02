<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChatController extends Controller
{
    // список чатов текущего пользователя
    public function index(Request $r)
    {
        $user = $r->user();

        $chats = Chat::query()
            ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->with([
                'messages' => fn ($q) => $q->latest()->limit(10),
                'users', // чтобы была pivot с muted / role
            ])
            ->get();

        return response()->json($chats);
    }

    // создание direct|group
    public function store(Request $r)
    {
        $owner = $r->user();

        $data = $r->validate([
            'type'           => ['required', Rule::in(['direct', 'group'])],
            'title'          => ['nullable', 'string', 'max:255'],

            // для direct — participants обязателен, для group — опционален
            'participants'   => [
                Rule::requiredIf(fn () => $r->input('type') === 'direct'),
                'array',
            ],
            'participants.*' => ['integer', 'exists:users,id'],
        ]);

        $contactIds = Contact::query()
        ->where('user_id', $owner->id)
        ->pluck('contact_user_id')
        ->all();

        $chat = Chat::create([
            'type'             => $data['type'],
            'title'            => $data['title'] ?? null,
            'owner_id'         => $owner->id,
            'muted_by_default' => $data['muted_by_default'] ?? 0,
        ]);

        // владелец всегда участник
        $chat->users()->syncWithoutDetaching([
            $owner->id => [
                'role'  => 'admin', // было 'owner'
                'muted' => (bool) $chat->muted_by_default,
            ],
        ]);

        // direct: ровно 1 собеседник (берём первого из массива)
        if ($data['type'] === 'direct') {
            $peerId = collect($data['participants'] ?? [])->first();

            // на всякий случай защитимся от чата "сам с собой"
            if (!$peerId || $peerId === $owner->id) {
                abort(422, 'peer must be other user');
            }

            abort_unless(
                in_array($peerId, $contactIds, true),
                422,
                'peer must be from contacts'
            );

            $chat->users()->syncWithoutDetaching([
                $peerId => [
                    'role'  => 'member',
                    'muted' => (bool) $chat->muted_by_default,
                ],
            ]);
        }

        // group: добавляем всех, если переданы
        if ($data['type'] === 'group' && !empty($data['participants'])) {
            $attach = [];
            $unique = collect($data['participants'])->unique();

            foreach ($unique as $uid) {
                if ($uid === $owner->id) {
                    continue;
                }
                
                abort_unless(
                    in_array($uid, $contactIds, true),
                    422,
                    'all participants must be from contacts'
                );

                $attach[$uid] = [
                    'role'  => 'member',
                    'muted' => (bool) $chat->muted_by_default,
                ];
            }

            if ($attach) {
                $chat->users()->syncWithoutDetaching($attach);
            }
        }

        return response()->json($chat->load('users'), 201);
    }

    // добавить участника (из контактов)
    public function addParticipant(Request $r, Chat $chat)
    {
        $data = $r->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        // разрешаем только участникам добавлять
        abort_unless($chat->users()->whereKey($r->user()->id)->exists(), 403);

                // добавляем только тех, кто уже в контактах текущего пользователя
                $inContacts = Contact::query()
                ->where('user_id', $r->user()->id)
                ->where('contact_user_id', $data['user_id'])
                ->exists();
    
            abort_unless($inContacts, 422, 'user must be from contacts');
    
        $chat->users()->syncWithoutDetaching([
            $data['user_id'] => [
                'role'  => 'member',
                'muted' => (bool) $chat->muted_by_default,
            ],
        ]);

        return response()->json(['ok' => true]);
    }

    // mute/unmute для текущего пользователя — переключаем флаг на pivot
    public function toggleMute(Request $r, Chat $chat)
    {
        $userId = $r->user()->id;

        // убедимся, что пользователь в чате
        $participant = $chat->users()->whereKey($userId)->first();
        abort_unless($participant, 403);

        $currentMuted = (bool) $participant->pivot->muted;

        // переключаем
        $chat->users()->updateExistingPivot($userId, [
            'muted' => !$currentMuted,
        ]);

        return response()->json([
            'ok'    => true,
            'muted' => !$currentMuted,
        ]);
    }
}
