<?php

declare(strict_types=1);

namespace App\Http\Requests\Records;

use App\DTOs\RecordInput;
use Illuminate\Foundation\Http\FormRequest;

class StoreRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // operation permissions arrive in Phase 3
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'owner_id' => ['nullable', 'integer'],
            'stage_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
            'data' => ['array'],
        ];
    }

    public function toInput(): RecordInput
    {
        /** @var array<string, mixed> $data */
        $data = $this->input('data', []);

        return new RecordInput(
            ownerId: $this->integerOrNull('owner_id'),
            stageId: $this->integerOrNull('stage_id'),
            status: $this->input('status'),
            data: $data,
        );
    }

    private function integerOrNull(string $key): ?int
    {
        $value = $this->input($key);

        return $value === null ? null : (int) $value;
    }
}
