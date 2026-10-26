<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('problem_id')->constrained()->cascadeOnDelete();
            $table->text('input');
            $table->text('expected_output');
            $table->boolean('is_hidden')->default(false);
            // The order of a problem's tests (UUID ids carry no order): seed order, then admin additions.
            $table->unsignedInteger('position')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_cases');
    }
};
