<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\RecordRepositoryInterface;
use App\Models\Record;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentRecordRepository implements RecordRepositoryInterface
{
    public function find(int $id): ?Record
    {
        return Record::query()->find($id);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Record
    {
        return Record::query()->create($attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function update(Record $record, array $attributes): Record
    {
        $record->update($attributes);

        return $record->refresh();
    }

    public function delete(Record $record): void
    {
        $record->delete();
    }

    /** @return LengthAwarePaginator<int, Record> */
    public function paginateForEntityType(int $entityTypeId, int $perPage = 25): LengthAwarePaginator
    {
        return Record::query()
            ->where('entity_type_id', $entityTypeId)
            ->latest('id')
            ->paginate($perPage);
    }
}
