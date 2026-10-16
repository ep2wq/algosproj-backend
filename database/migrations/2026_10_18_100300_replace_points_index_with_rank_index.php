<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // /leaderboard/me counts users with more points who are not banned. With the banned_at
        // filter, users_points_index no longer covered that count and MySQL scanned the whole
        // table; (banned_at, points) answers it from the index alone, so it replaces it.
        Schema::table('users', function (Blueprint $table) {
            $table->index(['banned_at', 'points'], 'users_rank_index');
            $table->dropIndex(['points']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('points');
            $table->dropIndex('users_rank_index');
        });
    }
};
