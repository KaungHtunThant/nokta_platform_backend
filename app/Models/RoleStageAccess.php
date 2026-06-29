<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

/**
 * One (role, stage) capability row (ARCHITECTURE §6). Thin model: the enforcement lives in
 * App\Services\Stages\StageTransitionPolicy and App\Policies, not here.
 */
class RoleStageAccess extends BaseModel
{
    protected $table = 'role_stage_access';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'role_id', 'stage_id', 'can_move_from', 'can_move_to', 'can_view',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'can_move_from' => 'boolean',
        'can_move_to' => 'boolean',
        'can_view' => 'boolean',
    ];

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }
}
