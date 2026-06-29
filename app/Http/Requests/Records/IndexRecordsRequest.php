<?php

declare(strict_types=1);

namespace App\Http\Requests\Records;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Parses the filter DSL + sort for the records list. `filters` is a JSON-encoded array of
 * {field, op, value}; `sort` + `dir` choose the order. Shape is normalised here so the controller
 * stays thin and RecordQueryBuilder receives clean input.
 */
class IndexRecordsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is gated by op:record.read
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filters' => ['nullable', 'string'],
            'sort' => ['nullable', 'string'],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return list<array{field: string, op: string, value: mixed}> */
    public function filters(): array
    {
        $raw = $this->input('filters');
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($item): ?array => is_array($item) && isset($item['field'], $item['op']) && array_key_exists('value', $item)
                ? ['field' => (string) $item['field'], 'op' => (string) $item['op'], 'value' => $item['value']]
                : null,
            $decoded,
        )));
    }

    /** @return array{field: string, dir: string}|null */
    public function sort(): ?array
    {
        $field = $this->input('sort');
        if (! is_string($field) || $field === '') {
            return null;
        }

        return ['field' => $field, 'dir' => $this->input('dir') === 'desc' ? 'desc' : 'asc'];
    }

    public function perPage(): int
    {
        return min(100, max(1, (int) $this->input('per_page', 25)));
    }
}
