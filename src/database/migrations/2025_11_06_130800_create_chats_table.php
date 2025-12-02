<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['direct','group'])->index();     // индекс по типу
            $table->string('title')->nullable();
            $table->foreignId('owner_id')->nullable()
                  ->constrained('users')->nullOnDelete()->index(); // FK users
            $table->boolean('muted_by_default')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('chats');
    }
};
