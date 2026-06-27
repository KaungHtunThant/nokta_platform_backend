<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Per-tenant key/value config. First tenant-owned model — extends BaseModel, so it
 * inherits BelongsToTenant (row-level isolation + auto tenant_id) and auditing.
 */
class TenantSetting extends BaseModel
{
    /** @var list<string> */
    protected $fillable = ['tenant_id', 'key', 'value'];

    /** @var array<string, string> */
    protected $casts = ['value' => 'array'];
}
