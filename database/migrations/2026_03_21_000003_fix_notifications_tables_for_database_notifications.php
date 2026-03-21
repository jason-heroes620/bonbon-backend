<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            $isDatabaseNotificationsTable = Schema::hasColumn('notifications', 'notifiable_type')
                && Schema::hasColumn('notifications', 'notifiable_id')
                && Schema::hasColumn('notifications', 'type')
                && Schema::hasColumn('notifications', 'data');

            if (!$isDatabaseNotificationsTable && !Schema::hasTable('push_notifications')) {
                Schema::rename('notifications', 'push_notifications');
            }
        }

        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->uuidMorphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('push_notifications') && !Schema::hasTable('notifications')) {
            Schema::rename('push_notifications', 'notifications');
            return;
        }

        if (Schema::hasTable('push_notifications') && Schema::hasTable('notifications')) {
            Schema::dropIfExists('notifications');
            Schema::rename('push_notifications', 'notifications');
        }
    }
};

