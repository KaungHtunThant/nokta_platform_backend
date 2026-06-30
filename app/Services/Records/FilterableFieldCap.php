<?php

declare(strict_types=1);

namespace App\Services\Records;

use App\Models\FieldDefinition;
use App\Models\TenantSetting;
use Illuminate\Validation\ValidationException;

/**
 * Performance ceiling (Phase 7): bounds how many filterable (EAV-projected) field definitions a tenant
 * may have. The limit is a config default, overridable per tenant via tenant_settings
 * (`limits => {filterable_fields: N}`). Counts/queries run under the active tenant's global scope.
 */
final class FilterableFieldCap
{
    public function limit(): int
    {
        $override = TenantSetting::query()->where('key', 'limits')->first()?->value['filterable_fields'] ?? null;

        return $override !== null ? (int) $override : (int) config('records.max_filterable_fields', 20);
    }

    public function used(): int
    {
        return FieldDefinition::query()->where('is_filterable', true)->count();
    }

    public function remaining(): int
    {
        return max(0, $this->limit() - $this->used());
    }

    /** Throw when the tenant is already at its filterable-field limit. */
    public function assertCanAdd(): void
    {
        if ($this->used() >= $this->limit()) {
            throw ValidationException::withMessages([
                'is_filterable' => "This tenant has reached its filterable-field limit ({$this->limit()}).",
            ]);
        }
    }
}
