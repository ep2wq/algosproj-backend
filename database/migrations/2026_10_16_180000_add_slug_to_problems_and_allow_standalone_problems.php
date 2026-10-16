<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A problem without a lesson is a standalone challenge. Each problem gets a slug for
        // its /challenges page.
        Schema::table('problems', function (Blueprint $table) {
            $table->foreignId('lesson_id')->nullable()->change();
            $table->string('slug')->nullable()->unique()->after('lesson_id');
        });

        // Existing problems take their lesson's slug. A lesson has one problem today; a second
        // problem of the same lesson would get its id appended so the slug stays unique.
        $used = [];
        foreach (DB::table('problems')->join('lessons', 'lessons.id', '=', 'problems.lesson_id')
            ->orderBy('problems.id')->get(['problems.id', 'lessons.slug']) as $row) {
            $slug = isset($used[$row->slug]) ? $row->slug.'-'.$row->id : $row->slug;
            $used[$slug] = true;
            DB::table('problems')->where('id', $row->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
            // Fails while standalone problems (null lesson_id) exist; remove them first.
            $table->foreignId('lesson_id')->nullable(false)->change();
        });
    }
};
