<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The owner's activity filters (?user=<email>, ?event=) and the admin user list's ?role= filter
    // all order by created_at desc, id desc. Without these, MySQL scanned the whole table (or the
    // whole created_at index) for both the page and its count. Each index ends in created_at, and
    // InnoDB adds the id, so the filtered rows are read already in order (docs/performance-backend.md).
    public function up(): void
    {
        Schema::table('user_activity_logs', function (Blueprint $table) {
            $table->index(['email', 'created_at']);
            $table->index(['event', 'created_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->index(['role', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('user_activity_logs', function (Blueprint $table) {
            $table->dropIndex(['email', 'created_at']);
            $table->dropIndex(['event', 'created_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'created_at']);
        });
    }
};
