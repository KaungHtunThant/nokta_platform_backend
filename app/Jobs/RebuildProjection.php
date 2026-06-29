<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EntityType;
use App\Models\Record;
use App\Models\RecordValue;
use App\Services\Records\FieldProjector;
use App\Support\Tenancy\TenantManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Re-derives the EAV projection (record_values) for one entity type from records.data — proving JSON
 * is authoritative and the projection is disposable (ARCHITECTURE §3). Runs off-request; sets tenant
 * context itself since the queue worker has none. Wipe-then-rebuild keeps it correct after field-def
 * changes (e.g. a field becoming filterable, or no longer projected).
 */
final class RebuildProjection implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $entityTypeId,
    ) {}

    public function handle(TenantManager $tenants, FieldProjector $projector): void
    {
        $tenants->set($this->tenantId);

        $type = EntityType::query()->findOrFail($this->entityTypeId);
        $defs = $type->fieldDefinitions()->get();

        $recordIds = Record::query()->where('entity_type_id', $type->id)->pluck('id');
        RecordValue::query()->whereIn('record_id', $recordIds)->delete();

        Record::query()
            ->where('entity_type_id', $type->id)
            ->chunkById(500, function ($records) use ($defs, $projector): void {
                foreach ($records as $record) {
                    $projector->sync($record, $defs, $record->data ?? []);
                    $record->searchable(); // re-index (no-op under the null driver)
                }
            });
    }
}
