<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $projects = Project::query()
            ->with('owner:id,name', 'users:id,name')
            ->when(! $user->isAdmin(), fn ($q) => $q->where(function ($inner) use ($user) {
                $inner->whereHas('users', fn ($rel) => $rel->whereKey($user->id))
                    ->orWhere('owner_id', $user->id);
            }))
            ->latest('id')
            ->get()
            ->map(fn (Project $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'status_color' => $p->status->color(),
                'start_date' => $p->start_date?->toDateString(),
                'delivery_date' => $p->delivery_date?->toDateString(),
                'owner' => $p->owner ? [
                    'id' => $p->owner->id,
                    'name' => $p->owner->name,
                ] : null,
                'members_count' => $p->users->count(),
            ]);

        return Inertia::render('Dashboard', [
            'projects' => $projects,
            'can' => [
                'create' => $user->can('create', Project::class),
            ],
        ]);
    }
}
