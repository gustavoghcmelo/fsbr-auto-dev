<?php

namespace App\Models;

use App\Enums\FinalQaStatus;
use Database\Factories\RequirementQaRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property FinalQaStatus $status
 * @property ?Carbon $evaluated_at
 * @property int $run_number
 */
#[Fillable([
    'requirement_id',
    'evaluated_by',
    'run_number',
    'status',
    'observations',
    'evaluated_at',
])]
class RequirementQaRun extends Model
{
    /** @use HasFactory<RequirementQaRunFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => FinalQaStatus::class,
            'evaluated_at' => 'datetime',
            'run_number' => 'integer',
        ];
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
