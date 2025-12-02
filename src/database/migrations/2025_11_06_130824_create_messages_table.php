<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // автор
            $table->text('body');
            $table->foreignId('forwarded_from_message_id')->nullable()
                  ->constrained('messages')->nullOnDelete(); // самоссылка
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->index('chat_id');
            $table->index('user_id');
            $table->index('created_at');
        });
    }
    public function down(): void {
        Schema::dropIfExists('messages');
    }
};
