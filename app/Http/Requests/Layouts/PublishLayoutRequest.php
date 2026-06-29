<?php

declare(strict_types=1);

namespace App\Http\Requests\Layouts;

use Illuminate\Foundation\Http\FormRequest;

class PublishLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is gated by op:manage.layouts
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
        ];
    }

    public function version(): int
    {
        return (int) $this->input('version');
    }
}
