<?php

declare(strict_types=1);

namespace App\Http\Requests\Pipelines;

use Illuminate\Foundation\Http\FormRequest;

class StorePipelineRequest extends FormRequest
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
            'position' => ['nullable', 'integer'],
        ];
    }
}
