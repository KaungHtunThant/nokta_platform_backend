<?php

declare(strict_types=1);

namespace App\Http\Requests\Stages;

use Illuminate\Foundation\Http\FormRequest;

class StoreStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // operation permissions arrive in Phase 3
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:255'],
            'label' => ['required', 'array'],
            'color' => ['nullable', 'string', 'max:32'],
            'position' => ['nullable', 'integer'],
            'is_initial' => ['boolean'],
            'is_won' => ['boolean'],
            'is_lost' => ['boolean'],
            'rules' => ['array'],
            'rules.*.rule' => ['required_with:rules', 'string'],
            'rules.*.value' => ['present'],
        ];
    }

    /**
     * @return list<array{rule: string, value: mixed}>
     */
    public function ruleRows(): array
    {
        /** @var list<array{rule: string, value: mixed}> $rules */
        $rules = $this->input('rules', []);

        return $rules;
    }
}
