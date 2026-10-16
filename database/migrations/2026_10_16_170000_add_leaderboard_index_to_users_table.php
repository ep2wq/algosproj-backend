<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Matches the leaderboard's ORDER BY points DESC, created_at, id (id is the primary key,
        // which every index already ends with), so the top rows are read in order without a sort.
        Schema::table('users', function (Blueprint $table) {
            $table->rawIndex('points desc, created_at', 'users_leaderboard_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_leaderboard_index');
        });
    }
};
