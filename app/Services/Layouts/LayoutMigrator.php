<?php

declare(strict_types=1);

namespace App\Services\Layouts;

/**
 * Migrates a layout schema document from its stored schema_version up to CURRENT_VERSION by running
 * registered upcasters in order (ARCHITECTURE §5). Runs on READ so stale docs render correctly; the
 * migrated form is persisted on the next save. Renderers additionally tolerate missing nodes/fields,
 * so a doc never has to be migrated to render.
 *
 * Adding a schema change: bump CURRENT_VERSION and register an upcaster from the previous version.
 */
final class LayoutMigrator
{
    public const CURRENT_VERSION = 2;

    /** @var array<int, callable(array<string, mixed>): array<string, mixed>> from-version => upcaster */
    private array $upcasters;

    public function __construct()
    {
        $this->upcasters = [
            // v1 → v2: a bare-string `permission` becomes the structured `{ui: ...}` the renderer wants.
            1 => fn (array $schema): array => $this->normalizePermission($schema),
        ];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array{schema: array<string, mixed>, schema_version: int}
     */
    public function migrate(array $schema, int $fromVersion): array
    {
        $version = $fromVersion;

        while ($version < self::CURRENT_VERSION && isset($this->upcasters[$version])) {
            $schema = ($this->upcasters[$version])($schema);
            $version++;
        }

        return ['schema' => $schema, 'schema_version' => $version];
    }

    public function isStale(int $schemaVersion): bool
    {
        return $schemaVersion < self::CURRENT_VERSION;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function normalizePermission(array $node): array
    {
        if (isset($node['permission']) && is_string($node['permission'])) {
            $node['permission'] = ['ui' => $node['permission']];
        }

        if (isset($node['children']) && is_array($node['children'])) {
            $node['children'] = array_map(
                fn (mixed $child): mixed => is_array($child) ? $this->normalizePermission($child) : $child,
                $node['children'],
            );
        }

        return $node;
    }
}
