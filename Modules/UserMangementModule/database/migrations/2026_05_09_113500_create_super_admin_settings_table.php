<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('super_admin_settings')) {
            return;
        }

        Schema::create('super_admin_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('default_language', 10)->default('ar');
            $table->json('notifications')->nullable();
            $table->json('integrations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('super_admin_settings');
    }
};
