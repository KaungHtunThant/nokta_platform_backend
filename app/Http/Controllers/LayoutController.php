<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Layouts\PublishLayoutRequest;
use App\Http\Requests\Layouts\SaveLayoutVersionRequest;
use App\Http\Resources\LayoutResource;
use App\Http\Resources\LayoutVersionResource;
use App\Models\Layout;
use App\Models\User;
use App\Services\Layouts\LayoutEditService;
use App\Services\Layouts\LayoutMigrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Thin controller for layout reads + the builder's draft/publish/rollback flow. Mutations are gated
 * by op:manage.layouts (routes); persistence + versioning live in LayoutEditService. Reads migrate a
 * stale schema on the fly via LayoutMigrator (persisted on next save).
 */
final class LayoutController extends Controller
{
    public function __construct(
        private readonly LayoutEditService $editor,
        private readonly LayoutMigrator $migrator,
    ) {}

    public function show(string $surface, string $key): LayoutResource
    {
        $layout = Layout::query()
            ->where('surface', $surface)
            ->where('key', $key)
            ->where('is_active', true)
            ->firstOrFail();

        // Upcast a stale doc so it renders correctly; the migrated form persists on the next save.
        if ($this->migrator->isStale((int) $layout->schema_version)) {
            $migrated = $this->migrator->migrate($layout->schema ?? [], (int) $layout->schema_version);
            $layout->setAttribute('schema', $migrated['schema']);
            $layout->setAttribute('schema_version', $migrated['schema_version']);
        }

        return LayoutResource::make($layout);
    }

    public function versions(string $surface, string $key): AnonymousResourceCollection
    {
        $layout = $this->resolve($surface, $key);

        return LayoutVersionResource::collection($layout->versions()->orderByDesc('version')->get());
    }

    public function storeVersion(SaveLayoutVersionRequest $request, string $surface, string $key): JsonResponse
    {
        $layout = $this->resolve($surface, $key);

        /** @var User $user */
        $user = $request->user();
        $version = $this->editor->saveDraft($layout, $request->schema(), $request->note(), $user->id);

        return LayoutVersionResource::make($version)->response()->setStatusCode(201);
    }

    public function publish(PublishLayoutRequest $request, string $surface, string $key): LayoutResource
    {
        $layout = $this->editor->publish($this->resolve($surface, $key), $request->version());

        return LayoutResource::make($layout);
    }

    public function rollback(PublishLayoutRequest $request, string $surface, string $key): LayoutResource
    {
        $layout = $this->editor->rollback($this->resolve($surface, $key), $request->version());

        return LayoutResource::make($layout);
    }

    public function reset(Request $request, string $surface, string $key): LayoutResource
    {
        $layout = $this->editor->reset($this->resolve($surface, $key));

        return LayoutResource::make($layout);
    }

    private function resolve(string $surface, string $key): Layout
    {
        return Layout::query()->where('surface', $surface)->where('key', $key)->firstOrFail();
    }
}
