<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Pipelines\StorePipelineRequest;
use App\Http\Resources\PipelineResource;
use App\Models\EntityType;
use App\Models\Pipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PipelineController extends Controller
{
    public function index(string $entityTypeKey): AnonymousResourceCollection
    {
        $type = $this->resolveType($entityTypeKey);

        return PipelineResource::collection($type->pipelines()->get());
    }

    public function store(StorePipelineRequest $request, string $entityTypeKey): JsonResponse
    {
        $type = $this->resolveType($entityTypeKey);

        /** @var Pipeline $pipeline */
        $pipeline = $type->pipelines()->create([
            'key' => $request->string('key')->value(),
            'label' => $request->input('label'),
            'position' => (int) $request->input('position', 0),
        ]);

        return PipelineResource::make($pipeline)->response()->setStatusCode(201);
    }

    private function resolveType(string $key): EntityType
    {
        return EntityType::query()->where('key', $key)->firstOrFail();
    }
}
