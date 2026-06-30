<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Records\RecordLinkRequest;
use App\Http\Resources\RelatedRecordResource;
use App\Models\Record;
use App\Services\Records\RecordLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Thin controller for cross-record relations (Phase 6). Reads are gated by can:view,record and writes
 * by can:update,record; all link mutation + tenant/existence validation lives in RecordLinkService.
 */
final class RecordLinkController extends Controller
{
    public function __construct(private readonly RecordLinkService $links) {}

    public function index(Request $request, Record $record): AnonymousResourceCollection
    {
        $relationKey = $request->query('relation_key');
        $related = $this->links->relatedTo($record, is_string($relationKey) ? $relationKey : null);

        return RelatedRecordResource::collection($related);
    }

    public function store(RecordLinkRequest $request, Record $record): JsonResponse
    {
        $this->links->link($record, $request->toRecordId(), $request->relationKey());

        return response()->json(status: 201);
    }

    public function destroy(RecordLinkRequest $request, Record $record): JsonResponse
    {
        $this->links->unlink($record, $request->toRecordId(), $request->relationKey());

        return response()->json(status: 204);
    }
}
