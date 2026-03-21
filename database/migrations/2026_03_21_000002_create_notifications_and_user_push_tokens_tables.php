<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('notification_id')->primary();
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->enum('audience', ['all_users', 'user'])->default('all_users');
            $table->uuid('user_id')->nullable()->index();
            $table->uuid('created_by')->nullable()->index();
            $table->enum('status', ['draft', 'sent'])->default('draft');
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_push_tokens', function (Blueprint $table) {
            $table->uuid('user_push_token_id')->primary();
            $table->uuid('user_id')->index();
            $table->string('expo_push_token')->unique();
            $table->string('device_name')->nullable();
            $table->string('platform')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_push_tokens');
        Schema::dropIfExists('notifications');
    }
};

