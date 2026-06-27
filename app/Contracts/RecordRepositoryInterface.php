<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Record;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Persistence seam for the generalized Record model (DIP). Tenant scoping is applied
 * automatically by the model's global scope. Services depend on this, not on Eloquent.
 */
interface RecordRepositoryInterface
{
    public function find(int $id): ?Record;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Record;

    /** @param array<string, mixed> $attributes */
    public function update(Record $record, array $attributes): Record;

    public function delete(Record $record): void;

    /** @return LengthAwarePaginator<int, Record> */
    public function paginateForEntityType(int $entityTypeId, int $perPage = 25): LengthAwarePaginator;
}
