<?php

declare(strict_types=1);

namespace App\Http\Requests\Stages;

use Illuminate\Foundation\Http\FormRequest;

class ReorderStagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // operation permissions arrive in Phase 3
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer'],
        ];
    }

    /** @return list<int> ordered stage ids */
    public function order(): array
    {
        /** @var list<int> $order */
        $order = array_map('intval', $this->input('order', []));

        return $order;
    }
}
