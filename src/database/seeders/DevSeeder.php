<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Chat;
use App\Models\Message;

class DevSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => bcrypt('123456')]
        );

        $chat = Chat::firstOrCreate(
            ['type' => 'direct', 'owner_id' => $user->id]
        );

        $chat->users()->attach($user->id);

        Message::firstOrCreate([
            'chat_id' => $chat->id,
            'user_id' => $user->id,
            'body'    => 'Hello from seeder!'
        ]);
    }
}