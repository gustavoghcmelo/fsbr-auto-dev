<?php

namespace App\Models;

use App\Enums\SubtaskKind;
use App\Enums\SubtaskStatus;
use Database\Factories\RequirementSubtaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'requirement_id',
    'assignee_id',
    'created_by',
    'kind',
    'description',
    'status',
    'order',
    'done_at',
])]
class RequirementSubtask extends Model
{
    /** @use HasFactory<RequirementSubtaskFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'kind' => SubtaskKind::class,
            'status' => SubtaskStatus::class,
            'done_at' => 'datetime',
            'order' => 'integer',
        ];
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
