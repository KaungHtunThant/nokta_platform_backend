<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

/**
 * One (role, field) capability row (ARCHITECTURE §6). can_read/can_update are enforced server-side
 * by App\Services\Records\FieldGate; ui_visible only hides in the UI. Thin model — no logic here.
 */
class RoleFieldAccess extends BaseModel
{
    protected $table = 'role_field_access';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'role_id', 'field_definition_id', 'can_read', 'can_update', 'ui_visible',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'can_read' => 'boolean',
        'can_update' => 'boolean',
        'ui_visible' => 'boolean',
    ];

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return BelongsTo<FieldDefinition, $this> */
    public function fieldDefinition(): BelongsTo
    {
        return $this->belongsTo(FieldDefinition::class);
    }
}
