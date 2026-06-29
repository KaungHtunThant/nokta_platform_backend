<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One immutable saved revision of a layout's schema (ARCHITECTURE §5). Thin model — the
 * draft/publish/rollback ENGINE lives in App\Services\Layouts\LayoutEditService.
 */
class LayoutVersion extends BaseModel
{
    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'layout_id', 'version', 'schema_version', 'schema', 'note', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'schema' => 'array',
        'version' => 'integer',
        'schema_version' => 'integer',
    ];

    /** @return BelongsTo<Layout, $this> */
    public function layout(): BelongsTo
    {
        return $this->belongsTo(Layout::class);
    }
}
