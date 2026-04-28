<?php

namespace App\Http\Requests;

use App\Enums\ReopenScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReopenRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $scope = ReopenScope::tryFrom($this->input('scope', ''));
        if ($scope === null) {
            return false;
        }

        return $this->user()->can('create', [
            \App\Models\ReopenRequest::class,
            $this->route('requirement'),
            $scope,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::enum(ReopenScope::class)],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
