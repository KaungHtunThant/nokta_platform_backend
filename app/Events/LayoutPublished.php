<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Layout;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A layout was (re)published. Broadcast on the tenant-scoped config channel so connected runtimes and
 * other builder sessions invalidate their cached layout and re-render live (ARCHITECTURE §5).
 */
final class LayoutPublished implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly Layout $layout) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->layout->tenant_id}.config")];
    }

    public function broadcastAs(): string
    {
        return 'layout.published';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'surface' => $this->layout->surface,
            'key' => $this->layout->key,
            'version' => $this->layout->version,
        ];
    }
}
