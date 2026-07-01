<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\ComputedFieldEvaluator;
use App\Events\FieldDefinitionChanged;
use App\Http\Requests\Fields\StoreFieldDefinitionRequest;
use App\Http\Resources\FieldDefinitionResource;
use App\Models\EntityType;
use App\Models\FieldDefinition;
use App\Services\Records\FilterableFieldCap;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class FieldDefinitionController extends Controller
{
    public function __construct(
        private readonly FilterableFieldCap $filterableCap,
        private readonly ComputedFieldEvaluator $computed,
    ) {}

    public function store(StoreFieldDefinitionRequest $request, string $entityTypeKey): JsonResponse
    {
        $type = EntityType::query()->where('key', $entityTypeKey)->firstOrFail();

        // Computed fields: reject an unparseable expression up front (fast author feedback).
        if ($request->string('type')->value() === 'computed') {
            $expression = (string) data_get($request->input('ui'), 'expression', '');
            if (! $this->computed->parses($expression)) {
                throw ValidationException::withMessages(['ui.expression' => 'The computed-field expression is not valid.']);
            }
        }

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
