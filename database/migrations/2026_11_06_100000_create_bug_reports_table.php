<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reports from the "Report a bug" dialog (README "Bug reports"). Sanitized on the way in;
        // never holds an IP address, a password, a token, a cookie or a request body. The optional
        // parts are only stored when the user ticked them (included). `bugreports:prune` deletes old rows.
        Schema::create('bug_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contact_email')->nullable();
            $table->text('description');
            $table->string('status', 20)->default('new');
            $table->text('admin_note')->nullable();
            $table->string('page_path');
            $table->string('page_title')->nullable();
            $table->string('app_version', 50)->nullable();
            $table->string('language', 10)->nullable();
            $table->string('theme', 10)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('viewport', 20)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->json('error_context')->nullable();
            $table->json('failed_requests')->nullable();
            $table->json('console_errors')->nullable();
            $table->json('problem_context')->nullable();
            $table->json('included');
            $table->string('screenshot_path')->nullable();
            $table->timestamp('seen_at')->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->foreignUuid('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // The list (by status, newest first), the unseen badge and the prune.
            $table->index(['status', 'created_at']);
            $table->index('seen_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bug_reports');
    }
};
