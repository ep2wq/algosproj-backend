<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Forbidden or suspicious requests (README "Security events and IP bans"). Repeats from the
        // same IP within 10 minutes add to one row (count). Never holds a password, a token, a cookie
        // or a request body. `security:prune` deletes old rows.
        Schema::create('security_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('ip', 45);
            $table->string('user_agent')->nullable();
            $table->string('method', 10);
            $table->string('path');
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('detail')->nullable();
            $table->unsignedInteger('count')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('seen_at')->nullable();
            $table->timestamps();

            // The unseen badge and list, the aggregation lookup, the list order and the prune.
            $table->index(['seen_at', 'last_seen_at']);
            $table->index(['ip', 'type']);
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
