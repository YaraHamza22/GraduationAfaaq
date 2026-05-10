<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('virtual_sessions')) {
            return;
        }

        Schema::table('virtual_sessions', function (Blueprint $table) {
            $table->dropForeign(['integration_id']);
        });

        Schema::table('virtual_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('integration_id')->nullable()->change();
            $table->foreign('integration_id')
                ->references('id')
                ->on('external_integrations')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('virtual_sessions')) {
            return;
        }

        Schema::table('virtual_sessions', function (Blueprint $table) {
            $table->dropForeign(['integration_id']);
        });

        Schema::table('virtual_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('integration_id')->nullable(false)->change();
            $table->foreign('integration_id')
                ->references('id')
                ->on('external_integrations')
                ->cascadeOnDelete();
        });
    }
};
