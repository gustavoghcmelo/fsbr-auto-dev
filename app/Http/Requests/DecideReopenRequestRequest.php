<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideReopenRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('decide', $this->route('reopenRequest'));
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $isDeny = $this->input('decision') === 'denied';

        return [
            'decision' => ['required', Rule::in(['approved', 'denied'])],
            'decision_reason' => [
                $isDeny ? 'required' : 'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision_reason.required' => 'Informe o motivo da recusa.',
        ];
    }
}
