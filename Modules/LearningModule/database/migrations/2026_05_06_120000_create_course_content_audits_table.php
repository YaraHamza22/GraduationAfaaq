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
        Schema::create('course_content_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses', 'course_id')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained('lessons', 'lesson_id')->nullOnDelete();
            $table->string('verdict', 40);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_content_audits');
    }
};
