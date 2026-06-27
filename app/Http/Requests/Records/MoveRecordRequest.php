<?php

declare(strict_types=1);

namespace App\Http\Requests\Records;

use Illuminate\Foundation\Http\FormRequest;

class MoveRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // operation permission (stage.move) arrives in Phase 3
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'stage_id' => ['required', 'integer'],
            'comment' => ['nullable', 'string'],
            'position' => ['nullable', 'numeric'],
        ];
    }

    public function comment(): ?string
    {
        $comment = $this->input('comment');

        return is_string($comment) ? $comment : null;
    }

    public function position(): ?float
    {
        $position = $this->input('position');

        return $position === null ? null : (float) $position;
    }
}
