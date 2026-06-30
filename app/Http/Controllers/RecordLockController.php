<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\RecordResource;
use App\Models\Record;
use App\Services\Records\RecordLockService;

/**
 * Thin controller for locking/unlocking a record (Phase 7). Routes are gated by the object-level
 * RecordPolicy (can:lock / can:unlock); the immutability the lock implies is enforced in the write
 * + move services and the policy.
 */
final class RecordLockController extends Controller
{
    public function __construct(private readonly RecordLockService $locks) {}

    public function lock(Record $record): RecordResource
    {
        return RecordResource::make($this->locks->lock($record)->load('entityType'));
    }

    public function unlock(Record $record): RecordResource
    {
        return RecordResource::make($this->locks->unlock($record)->load('entityType'));
    }
}
