<?php

namespace App\Models;

use App\Enums\DevStatus;
use App\Enums\DorStatus;
use App\Enums\FinalQaStatus;
use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use Database\Factories\RequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property ?array<int, string> $flow_steps
 * @property ?array<int, string> $business_rules
 * @property ?array<int, string> $error_handling
 * @property ?array{api: array<int, string>, integrations: array<int, string>, other_systems: array<int, string>} $dependencies
 * @property ?array<int, string> $technical_risks
 * @property ?array{requirement_clear: bool, figma_aligned: bool, criteria_defined: bool, business_rules_clear: bool, no_ambiguity: bool, testable: bool} $dor_checklist
 */
#[Fillable([
    'project_id',
    'requirement_document_id',
    'created_by',
    'title',
    'description',
    'context',
    'acceptance_criteria',
    'gherkin',
    'status',
    'order',
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
    'dev_assignee_id',
    'dev_started_at',
    'dev_finished_at',
])]
class Requirement extends Model
{
    /** @use HasFactory<RequirementFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'acceptance_criteria' => 'array',
            'status' => RequirementStatus::class,
            'order' => 'integer',
            'approved_at' => 'datetime',
            'qa_approved_at' => 'datetime',
            'flow_steps' => 'array',
            'business_rules' => 'array',
            'error_handling' => 'array',
            'dependencies' => 'array',
            'technical_risks' => 'array',
            'dor_checklist' => 'array',
            'priority' => RequirementPriority::class,
            'dor_status' => DorStatus::class,
            'dev_status' => DevStatus::class,
            'story_points' => 'integer',
            'dev_started_at' => 'datetime',
            'dev_finished_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(RequirementDocument::class, 'requirement_document_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function qaApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qa_approved_by');
    }

    public function devAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dev_assignee_id');
    }

    public function sprints(): BelongsToMany
    {
        return $this->belongsToMany(Sprint::class, 'sprint_requirement')
            ->withPivot('position')
            ->withTimestamps();
    }

    public function reopenRequests(): HasMany
    {
        return $this->hasMany(ReopenRequest::class);
    }

    /**
     * @return HasMany<RequirementSubtask, $this>
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(RequirementSubtask::class);
    }

    /**
     * @return HasMany<RequirementQaRun, $this>
     */
    public function qaRuns(): HasMany
    {
        return $this->hasMany(RequirementQaRun::class);
    }

    /**
     * @return HasOne<RequirementQaRun, $this>
     */
    public function latestQaRun(): HasOne
    {
        return $this->qaRuns()->one()->latestOfMany('run_number');
    }

    public function isApproved(): bool
    {
        return $this->status === RequirementStatus::Approved;
    }

    public function isQaApproved(): bool
    {
        return $this->qa_approved_at !== null;
    }

    public function isDorApproved(): bool
    {
        return $this->dor_status === DorStatus::Approved;
    }

    public function isDevDone(): bool
    {
        return $this->dev_status === DevStatus::Done;
    }

    public function latestQaStatus(): ?FinalQaStatus
    {
        return $this->latestQaRun?->status;
    }

    public function isReadyForDev(): bool
    {
        return $this->isApproved() && $this->isQaApproved() && $this->isDorApproved();
    }

    /**
     * Escopo usado pelo módulo de QA/Testes para consumir apenas
     * os requisitos com aprovação efetiva do analista.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', RequirementStatus::Approved->value);
    }

    /**
     * Itens liberados para o Gerente de Projetos mandar pro backlog
     * (aprovados pelo analista E validados pelo QA).
     */
    public function scopeReadyForDev(Builder $query): Builder
    {
        return $query
            ->where('status', RequirementStatus::Approved->value)
            ->whereNotNull('qa_approved_at');
    }

    public function scopeAwaitingDor(Builder $query): Builder
    {
        return $query->whereIn('dor_status', [
            DorStatus::Draft->value,
            DorStatus::InQaValidation->value,
        ]);
    }

    public function scopeInDevelopment(Builder $query): Builder
    {
        return $query->where('dev_status', DevStatus::InProgress->value);
    }

    public function scopeAwaitingFinalQa(Builder $query): Builder
    {
        return $query
            ->where('dev_status', DevStatus::Done->value)
            ->where(function (Builder $q): void {
                $q->whereDoesntHave('latestQaRun')
                    ->orWhereHas(
                        'latestQaRun',
                        fn (Builder $sub) => $sub->where('status', '!=', FinalQaStatus::Approved->value)
                    );
            });
    }
}
