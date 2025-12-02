<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Chat;
use App\Models\User;

Broadcast::channel('private-chat.{chatId}', function (User $user, int $chatId): bool {
    return Chat::query()
        ->whereKey($chatId)
        ->whereHas('users', fn ($q) => $q->whereKey($user->id))
        ->exists();
});
