<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDomain extends Model
{
    /** @var list<string> */
    protected $fillable = ['tenant_id', 'domain', 'is_primary'];

    /** @var array<string, string> */
    protected $casts = ['is_primary' => 'boolean'];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
