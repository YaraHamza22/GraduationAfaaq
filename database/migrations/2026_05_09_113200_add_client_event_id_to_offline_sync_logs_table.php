<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('offline_sync_logs')) {
            return;
        }

        Schema::table('offline_sync_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('offline_sync_logs', 'client_event_id')) {
                $table->string('client_event_id', 120)->nullable()->after('device_id');
                $table->index(['user_id', 'device_id', 'client_event_id'], 'offline_sync_logs_user_device_event_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('offline_sync_logs')) {
            return;
        }

        Schema::table('offline_sync_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('offline_sync_logs', 'client_event_id')) {
                $table->dropIndex('offline_sync_logs_user_device_event_idx');
                $table->dropColumn('client_event_id');
            }
        });
    }
};
