<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\FieldDefinition;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A field definition was created/changed. Broadcast on the tenant-scoped config channel so clients
 * refresh their cached schema (the palette + renderer pick up the new/changed field live).
 */
final class FieldDefinitionChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly FieldDefinition $field) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->field->tenant_id}.config")];
    }

    public function broadcastAs(): string
    {
        return 'field-definition.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'entity_type_id' => $this->field->entity_type_id,
            'key' => $this->field->key,
        ];
    }
}
