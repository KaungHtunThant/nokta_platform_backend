<?php

declare(strict_types=1);

namespace App\Services\Board;

use App\Models\EntityType;
use App\Models\Pipeline;
use App\Models\Record;
use App\Models\Stage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Read model for the kanban board: the pipeline's ordered stages, each with its records paged
 * independently (per-column paging). Tenant scope is applied by the models' global scope.
 */
final class BoardReadService
{
    /**
     * @return array{pipeline: Pipeline, columns: array<int, array{stage: Stage, records: LengthAwarePaginator<int, Record>}>}
     */
    public function forEntityType(
        EntityType $type,
        ?int $pipelineId = null,
        ?int $onlyStageId = null,
        int $page = 1,
        int $perPage = 25,
    ): array {
        $pipeline = $this->resolvePipeline($type, $pipelineId);

        /** @var Collection<int, Stage> $stages */
        $stages = $pipeline->stages()->get();
        if ($onlyStageId !== null) {
            $stages = $stages->where('id', $onlyStageId)->values();
        }

        $columns = $stages->map(fn (Stage $stage): array => [
            'stage' => $stage,
            'records' => Record::query()
                ->where('entity_type_id', $type->id)
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
