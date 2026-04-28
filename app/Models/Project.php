<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'description',
    'github_repo_url',
    'start_date',
    'delivery_date',
    'forecast_hours',
    'status',
    'owner_id',
])]
class Project extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'delivery_date' => 'date',
            'forecast_hours' => 'decimal:2',
            'status' => ProjectStatus::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role_override')
            ->withTimestamps();
    }

    public function requirementDocuments(): HasMany
    {
        return $this->hasMany(RequirementDocument::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(Requirement::class);
    }

    public function testPlans(): HasMany
    {
        return $this->hasMany(TestPlan::class);
    }

    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class);
    }

    public function hasMember(User $user): bool
    {
        if ($this->owner_id === $user->id) {
            return true;
        }

        return $this->users()->whereKey($user->id)->exists();
    }

    /**
     * Papel efetivo do usuário no projeto. Prioridade:
     *   1. role_override no pivot (se membro explícito)
     *   2. profile global (se membro explícito sem override)
     *   3. profile global (se owner do projeto, mesmo fora do pivot — garante
     *      que o criador sempre tenha acesso)
     *   4. null (não está relacionado ao projeto)
     */
    public function effectiveRoleFor(User $user): ?string
    {
        $pivot = $this->users()
            ->whereKey($user->id)
            ->first()?->pivot;

        if ($pivot) {
            return $pivot->role_override ?: $user->profile?->slug;
        }

        if ($this->owner_id === $user->id) {
            return $user->profile?->slug;
        }

        return null;
    }
}
