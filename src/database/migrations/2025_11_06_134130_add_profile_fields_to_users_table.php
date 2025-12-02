<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname')->nullable()->unique()->after('email');
            $table->string('avatar_path')->nullable()->after('nickname');
            $table->boolean('email_hidden')->default(false)->after('avatar_path');

            $table->index('email');     // на всякий случай, если нет
            $table->index('nickname');  // продублирует unique для быстрых запросов
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['nickname']);
            $table->dropColumn(['nickname','avatar_path','email_hidden']);
        });
    }
};
