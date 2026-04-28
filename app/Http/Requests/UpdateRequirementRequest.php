<?php

namespace App\Http\Requests;

use App\Enums\RequirementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('requirement'));
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'context' => ['nullable', 'string'],
            'acceptance_criteria' => ['array'],
            'acceptance_criteria.*' => ['string'],
            'gherkin' => ['required', 'string'],
            'status' => ['required', Rule::enum(RequirementStatus::class)],
        ];
    }
}
