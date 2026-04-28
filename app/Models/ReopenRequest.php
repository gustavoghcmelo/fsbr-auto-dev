<?php

namespace App\Models;

use App\Enums\ReopenScope;
use App\Enums\ReopenStatus;
use App\Observers\ReopenRequestObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[ObservedBy(ReopenRequestObserver::class)]
#[Fillable([
    'requirement_id',
    'scope',
    'status',
    'requested_by',
    'reason',
    'decided_by',
    'decided_at',
    'decision_reason',
])]
class ReopenRequest extends Model
{
    protected $table = 'pipeline_reopen_requests';

    protected function casts(): array
    {
        return [
            'scope' => ReopenScope::class,
            'status' => ReopenStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'subject');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ReopenStatus::Pending->value);
    }

    public function isPending(): bool
    {
        return $this->status === ReopenStatus::Pending;
    }
}
