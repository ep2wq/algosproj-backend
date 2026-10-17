<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Arabic text next to each English prose column, same type. NULL means "not translated yet";
    // the API then falls back to the English column, per field.
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->string('title_ar')->nullable()->after('title');
        });
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('title_ar')->nullable()->after('title');
            $table->text('body_ar')->nullable()->after('body');
        });
        Schema::table('problems', function (Blueprint $table) {
            $table->string('title_ar')->nullable()->after('title');
            $table->text('description_ar')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn('title_ar');
        });
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['title_ar', 'body_ar']);
        });
        Schema::table('problems', function (Blueprint $table) {
            $table->dropColumn(['title_ar', 'description_ar']);
        });
    }
};
