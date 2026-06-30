<?php

declare(strict_types=1);

namespace App\Http\Requests\Records;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload for creating/removing an explicit record_link. Route is gated by can:update,record;
 * cross-tenant / existence checks happen in RecordLinkService.
 */
class RecordLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'to_record_id' => ['required', 'integer'],
            'relation_key' => ['required', 'string'],
        ];
    }

    public function toRecordId(): int
    {
        return (int) $this->input('to_record_id');
    }

    public function relationKey(): string
    {
        return (string) $this->input('relation_key');
    }
}
