<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('runner_logs', function (Blueprint $table) {
            $table->id();
            $table->string('runner_name')->index();
            $table->string('tag')->nullable()->index();
            $table->enum('type', ['once', 'always'])->default('once');
            $table->enum('status', ['started', 'completed', 'failed'])->index();
            $table->text('output')->nullable();
            $table->text('error')->nullable();
            $table->integer('execution_time')->nullable()->comment('Execution time in milliseconds');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['runner_name', 'status']);
            $table->index(['created_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('runner_logs');
    }
};