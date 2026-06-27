<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Pipeline;
use App\Models\Record;
use App\Models\Stage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Shapes the board read model: the pipeline + one column per stage, each carrying its records
 * (first/requested page) and per-column paging metadata.
 *
 * @property array{pipeline: Pipeline, columns: array<int, array{stage: Stage, records: LengthAwarePaginator<int, Record>}>} $resource
 */
class BoardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array{pipeline: Pipeline, columns: array<int, array{stage: Stage, records: LengthAwarePaginator<int, Record>}>} $board */
        $board = $this->resource;

        return [
            'pipeline' => PipelineResource::make($board['pipeline']),
            'columns' => (new Collection($board['columns']))->map(function (array $column): array {
                /** @var LengthAwarePaginator<int, Record> $records */
                $records = $column['records'];

                return [
                    'stage' => StageResource::make($column['stage']),
                    'records' => RecordResource::collection($records->items()),
                    'meta' => [
                        'total' => $records->total(),
                        'per_page' => $records->perPage(),
                        'current_page' => $records->currentPage(),
                        'last_page' => $records->lastPage(),
                    ],
                ];
            })->all(),
        ];
    }
}
