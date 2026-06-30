<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\AuditResource;
use App\Models\Record;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Thin controller: the audit trail for a record (Phase 7). Gated by op:audit.view; tenant isolation
 * holds because the record is route-model-bound (and globally tenant-scoped), so only audits of
 * records in the active tenant are reachable.
 */
final class RecordAuditController extends Controller
{
    public function index(Record $record): AnonymousResourceCollection
    {
        return AuditResource::collection($record->audits()->latest()->get());
    }
}
