<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use App\Models\Profile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Project::class);
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $profileSlugs = Profile::query()->pluck('slug')->all();

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'github_repo_url' => ['nullable', 'url', 'max:500'],
            'start_date' => ['nullable', 'date'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'forecast_hours' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'members' => ['array'],
            'members.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'members.*.role_override' => ['nullable', 'string', Rule::in($profileSlugs)],
        ];
    }
}
