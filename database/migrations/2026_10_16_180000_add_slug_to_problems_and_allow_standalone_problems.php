<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A problem without a lesson is a standalone challenge. Each problem gets a slug for
        // its /challenges page.
        Schema::table('problems', function (Blueprint $table) {
            $table->foreignUuid('lesson_id')->nullable()->change();
            $table->string('slug')->nullable()->unique()->after('lesson_id');
        });
        // The problems table is always empty here (the schema is built fresh), so no slugs are filled.
    }

    public function down(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
            // Fails while standalone problems (null lesson_id) exist; remove them first.
            $table->foreignUuid('lesson_id')->nullable(false)->change();
        });
    }
};
