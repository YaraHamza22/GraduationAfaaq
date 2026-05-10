e<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot: quizzes assigned to students (profiles).
     */
    public function up(): void
    {
        Schema::create('quiz_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['quiz_id', 'student_id']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_student');
    }
};
