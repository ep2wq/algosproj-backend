<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // IPs the owner banned (README "Security events and IP bans"). expires_at NULL = permanent.
        // BlockBannedIps answers them 403 before anything else runs.
        Schema::create('banned_ips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ip', 45)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->string('note')->nullable();
            $table->foreignUuid('banned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('blocked_count')->default(0);
            $table->timestamp('last_blocked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banned_ips');
    }
};
