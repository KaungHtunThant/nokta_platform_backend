<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\FieldDefinitionChanged;
use App\Http\Requests\Fields\StoreFieldDefinitionRequest;
use App\Http\Resources\FieldDefinitionResource;
use App\Models\EntityType;
use App\Models\FieldDefinition;
use App\Services\Records\FilterableFieldCap;
use Illuminate\Http\JsonResponse;

final class FieldDefinitionController extends Controller
{
    public function __construct(private readonly FilterableFieldCap $filterableCap) {}

    public function store(StoreFieldDefinitionRequest $request, string $entityTypeKey): JsonResponse
    {
        $type = EntityType::query()->where('key', $entityTypeKey)->firstOrFail();

        $isFilterable = $request->boolean('is_filterable');
        if ($isFilterable) {
            $this->filterableCap->assertCanAdd(); // performance ceiling (Phase 7)
        }

        /** @var FieldDefinition $field */
        $field = $type->fieldDefinitions()->create([
            'key' => $request->string('key')->value(),
            'type' => $request->string('type')->value(),
            'label' => $request->input('label'),
            'validation' => $request->input('validation'),
            'ui' => $request->input('ui'),
            'position' => (int) $request->input('position', 0),
            'storage_strategy' => $request->input('storage_strategy', 'json'),
            'is_filterable' => $isFilterable,
            'is_sortable' => $request->boolean('is_sortable'),
            'is_reportable' => $request->boolean('is_reportable'),
        ]);

        event(new FieldDefinitionChanged($field)); // live schema invalidation for clients/builders

        return FieldDefinitionResource::make($field)->response()->setStatusCode(201);
    }
}
