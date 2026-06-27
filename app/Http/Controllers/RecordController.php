<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Records\StoreRecordRequest;
use App\Http\Requests\Records\UpdateRecordRequest;
use App\Http\Resources\RecordResource;
use App\Models\EntityType;
use App\Models\Record;
use App\Services\Records\RecordWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Thin controller: resolve, delegate to the write service, return a Resource.
 * No business logic, no direct DB access (enforced by tests/Arch).
 */
final class RecordController extends Controller
{
    public function __construct(private readonly RecordWriteService $writer) {}

    public function index(string $entityTypeKey): AnonymousResourceCollection
    {
        $type = $this->resolveType($entityTypeKey);

        $records = Record::query()
            ->where('entity_type_id', $type->id)
            ->latest('id')
            ->paginate(25);

        return RecordResource::collection($records);
    }

    public function store(StoreRecordRequest $request, string $entityTypeKey): JsonResponse
    {
        $type = $this->resolveType($entityTypeKey);
        $record = $this->writer->create($type, $request->toInput());

        return RecordResource::make($record->load('entityType'))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Record $record): RecordResource
    {
        return RecordResource::make($record->load('entityType'));
    }

    public function update(UpdateRecordRequest $request, Record $record): RecordResource
    {
        $record = $this->writer->update($record->entityType, $record, $request->toInput());

        return RecordResource::make($record->load('entityType'));
    }

    public function destroy(Record $record): JsonResponse
    {
        $record->delete();

        return response()->json(status: 204);
    }

    private function resolveType(string $key): EntityType
    {
        return EntityType::query()->where('key', $key)->firstOrFail();
    }
}
