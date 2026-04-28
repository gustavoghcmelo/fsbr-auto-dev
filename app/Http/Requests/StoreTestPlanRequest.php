<?php

namespace App\Http\Requests;

use App\Enums\TestPlanStatus;
use App\Models\TestPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTestPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [TestPlan::class, $this->route('project')]);
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(TestPlanStatus::class)],
        ];
    }
}
