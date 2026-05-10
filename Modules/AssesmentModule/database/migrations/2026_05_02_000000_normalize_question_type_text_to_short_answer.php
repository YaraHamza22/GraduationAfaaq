<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Legacy rows used `text` while QuestionType enum expects `short_answer`.
     */
    public function up(): void
    {
        DB::table('questions')
            ->where('type', 'text')
            ->update(['type' => 'short_answer']);
    }

    public function down(): void
    {
        // Not reversible: cannot tell which `short_answer` rows were migrated from `text`.
    }
};
