<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Caching seam (Redis behind an interface). Controllers/Actions never touch the cache
 * directly — only read-model/cache services do (enforced by tests/Arch).
 * Keys/tags are tenant-scoped; invalidation is event-driven (see Phase 2/4).
 *
 * STUB: methods added when the board read-model lands (Phase 2).
 */
interface CacheRepositoryInterface
{
    // public function remember(string $key, array $tags, int $ttl, \Closure $callback): mixed;
    // public function invalidateTags(array $tags): void;
}
