<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Records\IndexRecordsRequest;
use App\Http\Requests\Records\StoreRecordRequest;
use App\Http\Requests\Records\UpdateRecordRequest;
use App\Http\Resources\RecordResource;
use App\Models\EntityType;
use App\Models\Record;
use App\Models\User;
use App\Services\Records\FieldGate;
use App\Services\Records\RecordQueryBuilder;
use App\Services\Records\RecordWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Thin controller: resolve, delegate to the write service, return a Resource.
 * No business logic, no direct DB access (enforced by tests/Arch). Field-level read/write
 * authorization is delegated to FieldGate (Phase 3): the actor is passed to the write service,
 * and readable field keys are stashed on the request for RecordResource to filter on.
 */
final class RecordController extends Controller
{
    public function __construct(
        private readonly RecordWriteService $writer,
        private readonly FieldGate $fieldGate,
        private readonly RecordQueryBuilder $queryBuilder,
    ) {}

    public function index(IndexRecordsRequest $request, string $entityTypeKey): AnonymousResourceCollection
    {
        $type = $this->resolveType($entityTypeKey);
        $this->applyReadableKeys($request, $type);

        $records = $this->queryBuilder
            ->for($type, $request->filters(), $request->sort())
            ->paginate($request->perPage());

        return RecordResource::collection($records);
    }

    public function store(StoreRecordRequest $request, string $entityTypeKey): JsonResponse
    {
        $type = $this->resolveType($entityTypeKey);
        $record = $this->writer->create($type, $request->toInput(), $this->actor($request));

        $this->applyReadableKeys($request, $type);

        return RecordResource::make($record->load('entityType'))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Record $record): RecordResource
    {
        $type = $record->entityType()->firstOrFail();
        $this->applyReadableKeys($request, $type);

        return RecordResource::make($record->load('entityType'));
    }

    public function update(UpdateRecordRequest $request, Record $record): RecordResource
    {
        $type = $record->entityType()->firstOrFail();
        $record = $this->writer->update($type, $record, $request->toInput(), $this->actor($request));

        $this->applyReadableKeys($request, $type);

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

    /** Stash the field keys this actor may read so RecordResource strips the rest. */
    private function applyReadableKeys(Request $request, EntityType $type): void
    {
        $actor = $this->actor($request);

        if ($actor !== null) {
            $request->attributes->set('readableFieldKeys', $this->fieldGate->readableKeysForType($actor, $type));
        }
    }

    private function actor(Request $request): ?User
    {
        /** @var User|null $user */
        $user = $request->user();

        return $user;
    }
}
