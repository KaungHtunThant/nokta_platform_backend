<?php

declare(strict_types=1);

namespace App\Http\Requests\Layouts;

use Illuminate\Foundation\Http\FormRequest;

class SaveLayoutVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is gated by op:manage.layouts
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'schema' => ['required', 'array'],
            'schema.type' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, mixed> */
    public function schema(): array
    {
        /** @var array<string, mixed> $schema */
        $schema = $this->input('schema', []);

        return $schema;
    }

    public function note(): ?string
    {
        $note = $this->input('note');

        return is_string($note) ? $note : null;
    }
}
