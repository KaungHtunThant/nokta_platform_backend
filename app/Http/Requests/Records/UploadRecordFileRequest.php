<?php

declare(strict_types=1);

namespace App\Http\Requests\Records;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Multipart payload for attaching a file to a record's `file` field. Route is gated by
 * can:update,record; field-level write authorization is enforced in RecordFileService (FieldGate).
 */
class UploadRecordFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'field_key' => ['required', 'string'],
            'file' => ['required', 'file', 'max:20480'],
        ];
    }

    public function fieldKey(): string
    {
        return (string) $this->input('field_key');
    }
}
