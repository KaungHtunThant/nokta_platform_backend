<?php

declare(strict_types=1);

namespace App\Services\Board;

use App\Models\EntityType;
use App\Models\Pipeline;
use App\Models\Record;
use App\Models\Stage;
use App\Services\Records\RecordQueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Read model for the kanban board: the pipeline's ordered stages, each with its records paged
 * independently (per-column paging). Tenant scope is applied by the models' global scope.
 * An optional filter DSL (Phase 4) is applied to every column via RecordQueryBuilder; the board
 * always orders within a column by position (custom sort is a list-surface concern).
 */
final class BoardReadService
{
    public function __construct(private readonly RecordQueryBuilder $queryBuilder) {}

    /**
     * @param  list<array{field: string, op: string, value: mixed}>  $filters
     * @return array{pipeline: Pipeline, columns: array<int, array{stage: Stage, records: LengthAwarePaginator<int, Record>}>}
     */
    public function forEntityType(
        EntityType $type,
        ?int $pipelineId = null,
        ?int $onlyStageId = null,
        int $page = 1,
        int $perPage = 25,
        array $filters = [],
    ): array {
        $pipeline = $this->resolvePipeline($type, $pipelineId);

        /** @var Collection<int, Stage> $stages */
        $stages = $pipeline->stages()->get();
        if ($onlyStageId !== null) {
            $stages = $stages->where('id', $onlyStageId)->values();
        }

        $base = $this->queryBuilder->filtered($type, $filters);

        $columns = $stages->map(fn (Stage $stage): array => [
            'stage' => $stage,
            'records' => (clone $base)
                ->where('stage_id', $stage->id)
                ->orderBy('position')
                ->orderBy('id')
                ->paginate(perPage: $perPage, page: $page),
        ])->all();

        return ['pipeline' => $pipeline, 'columns' => $columns];
    }

    private function resolvePipeline(EntityType $type, ?int $pipelineId): Pipeline
    {
        $query = Pipeline::query()->where('entity_type_id', $type->id);

        return $pipelineId !== null
            ? $query->findOrFail($pipelineId)
            : $query->orderBy('position')->firstOrFail();
    }
}
