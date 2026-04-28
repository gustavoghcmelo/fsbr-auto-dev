<?php

namespace App\Models;

use App\Enums\TestCasePriority;
use App\Enums\TestCaseStatus;
use App\Observers\TestCaseObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(TestCaseObserver::class)]
#[Fillable([
    'test_plan_id',
    'requirement_id',
    'created_by',
    'title',
    'preconditions',
    'steps',
    'expected_result',
    'gherkin',
    'priority',
    'status',
    'order',
])]
class TestCase extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'priority' => TestCasePriority::class,
            'status' => TestCaseStatus::class,
            'order' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TestPlan::class, 'test_plan_id');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'subject');
    }
}
