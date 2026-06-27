<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Support\Tenancy\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Two demo tenants (clinic brands), each with one member + a tenant-scoped setting.
        $manager = app(TenantManager::class);

        foreach (['nokta' => 'admin@nokta.test', 'mediceva' => 'admin@mediceva.test'] as $slug => $email) {
            $tenant = Tenant::firstOrCreate(
                ['slug' => $slug],
                ['name' => ['en' => ucfirst($slug)], 'status' => 'active']
            );

            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => ucfirst($slug).' Admin', 'password' => Hash::make('password')]
            );

            $tenant->users()->syncWithoutDetaching([$user->id => ['status' => 'active']]);

            $manager->set($tenant->id);
            TenantSetting::updateOrCreate(['key' => 'brand.color'], ['value' => ['hex' => $slug === 'nokta' ? '#dd3636' : '#2e74b5']]);
            $manager->forget();
        }

        // Phase 1: demo 'deal' entity type + fields + layouts.
        $this->call(DemoEntitySeeder::class);
    }
}
