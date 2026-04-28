<?php

namespace App\Http\Requests;

use App\Enums\TestCasePriority;
use App\Enums\TestCaseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTestCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [\App\Models\TestCase::class, $this->route('plan')]);
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'preconditions' => ['nullable', 'string'],
            'steps' => ['array'],
            'steps.*.keyword' => ['required', 'string', 'max:40'],
            'steps.*.text' => ['required', 'string'],
            'steps.*.type' => ['required', Rule::in(['precondition', 'action', 'assertion'])],
            'expected_result' => ['nullable', 'string'],
            'gherkin' => ['nullable', 'string'],
            'priority' => ['required', Rule::enum(TestCasePriority::class)],
            'status' => ['required', Rule::enum(TestCaseStatus::class)],
            'requirement_id' => ['nullable', 'integer', 'exists:requirements,id'],
        ];
    }
}
