<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('chat_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['member','admin'])->default('member');
            $table->boolean('muted')->default(false);
            $table->timestamps();

            $table->unique(['chat_id','user_id']);
            $table->index(['user_id','chat_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('chat_user');
    }
};
