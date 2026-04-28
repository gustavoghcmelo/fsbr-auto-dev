<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Observers\RequirementDocumentObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(RequirementDocumentObserver::class)]
#[Fillable([
    'project_id',
    'uploaded_by',
    'original_filename',
    'disk',
    'storage_path',
    'mime_type',
    'size_bytes',
    'status',
    'failure_reason',
])]
class RequirementDocument extends Model
{
    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'size_bytes' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(Requirement::class);
    }
}
