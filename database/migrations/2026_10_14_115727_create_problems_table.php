<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('problems', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('difficulty');
            $table->unsignedInteger('points');
            // Orders problems of the same lesson and standalone problems, oldest first (UUID ids carry no order).
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('problems');
    }
};
