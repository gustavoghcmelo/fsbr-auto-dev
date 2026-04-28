<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->timestamp('qa_approved_at')->nullable()->after('approved_by');
            $table->foreignId('qa_approved_by')
                ->nullable()
                ->after('qa_approved_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['project_id', 'qa_approved_at']);
        });
    }

    public function down(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('qa_approved_by');
            $table->dropColumn('qa_approved_at');
        });
    }
};
