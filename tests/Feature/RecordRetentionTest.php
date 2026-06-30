<?php

declare(strict_types=1);

use App\DTOs\RecordInput;
use App\Models\EntityType;
use App\Models\Record;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\Records\RecordWriteService;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Create a tenant with a deal entity + a record, in the tenant's context. Returns [tenant, record]. */
function seedRetentionTenant(string $slug): array
{
    $tenant = Tenant::create(['slug' => $slug, 'name' => ['en' => ucfirst($slug)]]);
    app(TenantManager::class)->set($tenant->id);

    $deal = EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal'], 'config' => ['title_field' => 'title']]);
    $deal->fieldDefinitions()->create(['key' => 'title', 'type' => 'text', 'label' => ['en' => 'Title'], 'validation' => ['required' => true], 'storage_strategy' => 'json', 'position' => 0]);

    $record = app(RecordWriteService::class)->create($deal, new RecordInput(null, null, null, ['title' => 'Old']));

    return [$tenant, $record];
}

it('soft-deletes only records past a tenant\'s retention window, and skips tenants without one', function () {
    // Tenant A: 30-day retention, with one stale and one fresh record.
    [$a, $stale] = seedRetentionTenant('reta');
    TenantSetting::query()->create(['tenant_id' => $a->id, 'key' => 'retention', 'value' => ['records_days' => 30]]);
    $fresh = app(RecordWriteService::class)->create(
        EntityType::query()->where('key', 'deal')->first(),
        new RecordInput(null, null, null, ['title' => 'Fresh'])
    );
    // Age the stale record beyond the window (explicit updated_at bypasses the auto-touch).
    Record::query()->whereKey($stale->id)->update(['updated_at' => now()->subDays(60)]);

    // Tenant B: a stale record but NO retention setting → must be untouched.
    [, $bRecord] = seedRetentionTenant('retb');
    Record::query()->whereKey($bRecord->id)->update(['updated_at' => now()->subDays(90)]);

    $this->artisan('records:prune')->assertSuccessful();

    app(TenantManager::class)->set($a->id);
    expect(Record::find($stale->id))->toBeNull()           // pruned
        ->and(Record::find($fresh->id))->not->toBeNull();  // within window

    app(TenantManager::class)->set($bRecord->tenant_id);
    expect(Record::find($bRecord->id))->not->toBeNull();   // no retention config → kept
});
