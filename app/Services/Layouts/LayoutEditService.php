<?php

declare(strict_types=1);

namespace App\Services\Layouts;

use App\Events\LayoutPublished;
use App\Models\FieldDefinition;
use App\Models\Layout;
use App\Models\LayoutVersion;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;

/**
 * Draft → publish → rollback engine for layouts (ARCHITECTURE §5). Every save appends an immutable
 * LayoutVersion (the draft history); publish points the live `layouts` row at a chosen version and
 * broadcasts; rollback is just publishing an older version; reset republishes the baseline. The live
 * doc is never destructively mutated — prior versions always remain restorable.
 */
final class LayoutEditService
{
    /**
     * Save a new draft version of the schema. Does not change what's live until published.
     *
     * @param  array<string, mixed>  $schema
     */
    public function saveDraft(Layout $layout, array $schema, ?string $note = null, ?int $userId = null): LayoutVersion
    {
        $this->ensureBaseline($layout);

        $next = (int) $layout->versions()->max('version') + 1;

        return LayoutVersion::query()->create([
            'layout_id' => $layout->id,
            'version' => $next,
            'schema_version' => LayoutMigrator::CURRENT_VERSION,
            'schema' => $schema,
            'note' => $note,
            'created_by' => $userId,
        ]);
    }

    /** Publish a saved version: copy it onto the live layout and broadcast for live invalidation. */
    public function publish(Layout $layout, int $version): Layout
    {
        /** @var LayoutVersion $target */
        $target = $layout->versions()->where('version', $version)->firstOrFail();

        $this->assertRequiredFieldsReachable($layout, $target->schema);

        $layout->update([
            'schema' => $target->schema,
            'schema_version' => $target->schema_version,
            'version' => $target->version,
            'is_active' => true,
            'published_at' => Date::now(),
        ]);

        event(new LayoutPublished($layout));

        return $layout->refresh();
    }

    /** Roll back to a prior version (semantically: republish it as the live doc). */
    public function rollback(Layout $layout, int $version): Layout
    {
        return $this->publish($layout, $version);
    }

    /** Reset to the system default — the earliest (baseline) version. */
    public function reset(Layout $layout): Layout
    {
        $this->ensureBaseline($layout);

        return $this->publish($layout, (int) $layout->versions()->min('version'));
    }

    /** Snapshot the current live doc as a version if no history exists yet (pre-Phase-5 layouts). */
    private function ensureBaseline(Layout $layout): void
    {
        if ($layout->versions()->exists()) {
            return;
        }

        LayoutVersion::query()->create([
            'layout_id' => $layout->id,
            'version' => $layout->version,
            'schema_version' => $layout->schema_version,
            'schema' => $layout->schema,
            'note' => 'baseline',
            'created_by' => $layout->created_by,
        ]);
    }

    /**
     * Guard: every REQUIRED field of a form layout's entity type must be bound somewhere in the tree,
     * so publishing can't strand a required field out of reach. (Form surface only.)
     *
     * @param  array<string, mixed>  $schema
     */
    private function assertRequiredFieldsReachable(Layout $layout, array $schema): void
    {
        if ($layout->surface !== 'form' || $layout->entity_type_id === null) {
            return;
        }

        $required = FieldDefinition::query()
            ->where('entity_type_id', $layout->entity_type_id)
            ->get()
            ->filter(fn (FieldDefinition $f): bool => $f->isRequired())
            ->pluck('key')
            ->all();

        $bound = $this->boundFieldKeys($schema);

        $missing = array_values(array_diff($required, $bound));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'schema' => 'Required fields are not reachable in this layout: '.implode(', ', $missing),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function boundFieldKeys(array $node): array
    {
        $keys = [];

        $field = $node['binding']['field'] ?? null;
        if (is_string($field)) {
            $keys[] = $field;
        }

        /** @var list<array<string, mixed>> $children */
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        foreach ($children as $child) {
            $keys = [...$keys, ...$this->boundFieldKeys($child)];
        }

        return $keys;
    }
}
