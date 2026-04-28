<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('preconditions')->nullable();
            $table->json('steps');
            $table->text('expected_result')->nullable();
            $table->text('gherkin')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('active');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['test_plan_id', 'order']);
            $table->index(['test_plan_id', 'requirement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_cases');
    }
};
