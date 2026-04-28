<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->text('objective')->nullable()->after('title');
            $table->string('user_story_role', 255)->nullable()->after('objective');
            $table->string('user_story_action', 255)->nullable()->after('user_story_role');
            $table->string('user_story_benefit', 255)->nullable()->after('user_story_action');
            $table->text('functional_description')->nullable()->after('user_story_benefit');
            $table->string('figma_url', 2048)->nullable()->after('functional_description');
            $table->text('figma_notes')->nullable()->after('figma_url');
            $table->json('flow_steps')->nullable()->after('figma_notes');
            $table->json('business_rules')->nullable()->after('flow_steps');
            $table->json('error_handling')->nullable()->after('business_rules');
            $table->json('dependencies')->nullable()->after('error_handling');
            $table->json('technical_risks')->nullable()->after('dependencies');
            $table->string('priority')->default('medium')->after('technical_risks');
            $table->unsignedSmallInteger('story_points')->nullable()->after('priority');
            $table->string('dor_status')->default('draft')->after('qa_approved_by');
            $table->json('dor_checklist')->nullable()->after('dor_status');
            $table->text('qa_observations')->nullable()->after('dor_checklist');
            $table->string('dev_status')->default('not_started')->after('qa_observations');
            $table->foreignId('dev_assignee_id')->nullable()->after('dev_status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('dev_started_at')->nullable()->after('dev_assignee_id');
            $table->timestamp('dev_finished_at')->nullable()->after('dev_started_at');

            $table->index(['project_id', 'dor_status']);
            $table->index(['project_id', 'dev_status']);
            $table->index(['project_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'priority']);
            $table->dropIndex(['project_id', 'dev_status']);
            $table->dropIndex(['project_id', 'dor_status']);

            $table->dropConstrainedForeignId('dev_assignee_id');

            $table->dropColumn([
                'objective',
                'user_story_role',
                'user_story_action',
                'user_story_benefit',
                'functional_description',
                'figma_url',
                'figma_notes',
                'flow_steps',
                'business_rules',
                'error_handling',
                'dependencies',
                'technical_risks',
                'priority',
                'story_points',
                'dor_status',
                'dor_checklist',
                'qa_observations',
                'dev_status',
                'dev_started_at',
                'dev_finished_at',
            ]);
        });
    }
};
