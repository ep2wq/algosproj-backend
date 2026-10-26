<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('problem_id')->constrained()->cascadeOnDelete();
            $table->string('language');
            $table->mediumText('code');
            $table->string('status');
            $table->unsignedInteger('passed_count');
            $table->unsignedInteger('total_count');
            $table->unsignedInteger('runtime_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
