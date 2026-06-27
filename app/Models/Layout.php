<?php

declare(strict_types=1);

namespace App\Models;

class Layout extends BaseModel
{
    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'entity_type_id', 'surface', 'key', 'scope', 'label',
        'is_active', 'version', 'schema_version', 'schema', 'published_at', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'label' => 'array',
        'schema' => 'array',
        'is_active' => 'boolean',
        'version' => 'integer',
        'schema_version' => 'integer',
        'published_at' => 'datetime',
    ];
}
