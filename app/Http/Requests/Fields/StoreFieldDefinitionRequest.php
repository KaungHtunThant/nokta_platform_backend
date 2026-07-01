<?php

declare(strict_types=1);

namespace App\Http\Requests\Fields;

use Illuminate\Foundation\Http\FormRequest;

class StoreFieldDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by ManageFields op-permission in Phase 3
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]*$/'],
            'type' => ['required', 'string'],
            'label' => ['required', 'array'],
            'validation' => ['nullable', 'array'],
            'ui' => ['nullable', 'array'],
            // Computed fields carry their expression + result type in `ui`; the expression is required
            // for a computed field and its syntax is validated in the controller (parses()).
            'ui.expression' => ['nullable', 'required_if:type,computed', 'string', 'max:2000'],
            'ui.result_type' => ['nullable', 'in:text,number,date,bool'],
            'position' => ['nullable', 'integer'],
            'storage_strategy' => ['nullable', 'in:column,json,eav'],
            'is_filterable' => ['nullable', 'boolean'],
            'is_sortable' => ['nullable', 'boolean'],
            'is_reportable' => ['nullable', 'boolean'],
        ];
    }
}
