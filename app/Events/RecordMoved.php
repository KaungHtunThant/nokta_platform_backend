<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\EntityType;
use App\Models\Record;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A record changed stage. Broadcast ONLY on the tenant-prefixed board channel for its entity type,
 * so a second tenant's client can never receive it (channel auth enforces membership + tenant match).
 */
final class RecordMoved implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Record $record,
        public readonly ?int $fromStageId,
        public readonly int $toStageId,
    ) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        /** @var EntityType $entityType */
        $entityType = $this->record->entityType;

        return [new PrivateChannel("tenant.{$this->record->tenant_id}.entity.{$entityType->key}.board")];
    }

    public function broadcastAs(): string
    {
        return 'record.moved';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->record->id,
            'from_stage_id' => $this->fromStageId,
            'stage_id' => $this->toStageId,
            'position' => $this->record->position,
            'data' => $this->record->data ?? [],
        ];
    }
}
