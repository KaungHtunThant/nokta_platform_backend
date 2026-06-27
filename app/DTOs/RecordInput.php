<?php

declare(strict_types=1);

namespace App\DTOs;

use Spatie\LaravelData\Data;

/**
 * Typed input carrier from the HTTP layer into the RecordWriteService.
 * `data` holds custom field values keyed by field definition key.
 */
final class RecordInput extends Data
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public readonly ?int $ownerId,
        public readonly ?int $stageId,
        public readonly ?string $status,
        public readonly array $data,
    ) {}
}
