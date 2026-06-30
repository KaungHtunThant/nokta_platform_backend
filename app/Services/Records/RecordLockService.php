<?php

declare(strict_types=1);

namespace App\Services\Records;

use App\Contracts\RecordRepositoryInterface;
use App\Models\Record;

/**
 * Append-only / signed clinical records (Phase 7). Locking flips the locked flag through the write
 * repository (so the change is audited like any other). Immutability itself is enforced by
 * RecordWriteService / RecordMoveService / RecordPolicy — this service only toggles the flag.
 */
final class RecordLockService
{
    public function __construct(private readonly RecordRepositoryInterface $records) {}

    public function lock(Record $record): Record
    {
        return $this->records->update($record, ['is_locked' => true]);
    }

    public function unlock(Record $record): Record
    {
        return $this->records->update($record, ['is_locked' => false]);
    }
}
