<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Record;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Support\Tenancy\TenantManager;
use Illuminate\Console\Command;

/**
 * Tenant data-retention (Phase 7). Each tenant may set `retention => {records_days: N}` in
 * tenant_settings; records untouched for longer than that window are soft-deleted. Conservative and
 * opt-in: a tenant with no retention setting (or N<=0) is never pruned. Runs per tenant inside that
 * tenant's context so the global scope keeps the prune tenant-isolated.
 */
final class PruneExpiredRecords extends Command
{
    protected $signature = 'records:prune';

    protected $description = 'Soft-delete records older than each tenant\'s configured retention window.';

    public function handle(TenantManager $tenants): int
    {
        foreach (Tenant::all() as $tenant) {
            $tenants->set($tenant->id);

            $days = $this->retentionDays();
            if ($days > 0) {
                $cutoff = now()->subDays($days);
                $pruned = Record::query()->where('updated_at', '<', $cutoff)->delete();
                $this->info("tenant {$tenant->slug}: pruned {$pruned} record(s) older than {$days}d.");
            }

            $tenants->forget();
        }

        return self::SUCCESS;
    }

    private function retentionDays(): int
    {
        $setting = TenantSetting::query()->where('key', 'retention')->first();

        return (int) ($setting?->value['records_days'] ?? 0);
    }
}
