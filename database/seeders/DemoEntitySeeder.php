<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EntityType;
use App\Models\Layout;
use App\Models\Tenant;
use App\Support\Tenancy\TenantManager;
use Illuminate\Database\Seeder;

/**
 * Phase 1 demo: a 'deal' entity type with 8 fields + form/detail layouts, for the Nokta tenant.
 * Proves the config-driven engine renders with zero entity-specific code.
 */
class DemoEntitySeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'nokta')->first();
        if ($tenant === null) {
            return;
        }

        $manager = app(TenantManager::class);
        $manager->set($tenant->id);

        $deal = EntityType::query()->firstOrCreate(
            ['key' => 'deal'],
            ['label' => ['en' => 'Deal', 'ar' => 'صفقة'], 'icon' => 'pi-briefcase', 'supports_pipeline' => true]
        );

        foreach ($this->fields() as $position => $field) {
            $deal->fieldDefinitions()->updateOrCreate(
                ['key' => $field['key']],
                array_merge($field, ['position' => $position, 'storage_strategy' => 'json'])
            );
        }

        $this->upsertLayout($tenant->id, $deal->id, 'form', 'deal.form', $this->formSchema());
        $this->upsertLayout($tenant->id, $deal->id, 'detail', 'deal.detail', $this->detailSchema());

        $manager->forget();
    }

    /** @return list<array<string, mixed>> */
    private function fields(): array
    {
        return [
            ['key' => 'title', 'type' => 'text', 'label' => ['en' => 'Title'], 'validation' => ['required' => true]],
            ['key' => 'note', 'type' => 'textarea', 'label' => ['en' => 'Note']],
            ['key' => 'email', 'type' => 'email', 'label' => ['en' => 'Email']],
            ['key' => 'budget', 'type' => 'money', 'label' => ['en' => 'Budget']],
            ['key' => 'age', 'type' => 'number', 'label' => ['en' => 'Age']],
            ['key' => 'appointment_date', 'type' => 'date', 'label' => ['en' => 'Appointment date']],
            ['key' => 'vip', 'type' => 'bool', 'label' => ['en' => 'VIP']],
            ['key' => 'priority', 'type' => 'select', 'label' => ['en' => 'Priority'],
                'ui' => ['options' => [
                    ['key' => 'low', 'label' => ['en' => 'Low']],
                    ['key' => 'medium', 'label' => ['en' => 'Medium']],
                    ['key' => 'high', 'label' => ['en' => 'High']],
                ]],
            ],
        ];
    }

    /** @param array<string, mixed> $schema */
    private function upsertLayout(int $tenantId, int $entityTypeId, string $surface, string $key, array $schema): void
    {
        Layout::query()->updateOrCreate(
            ['surface' => $surface, 'key' => $key],
            ['entity_type_id' => $entityTypeId, 'schema' => $schema, 'is_active' => true, 'label' => ['en' => ucfirst($surface)]]
        );
    }

    /** @return array<string, mixed> */
    private function formSchema(): array
    {
        return [
            'type' => 'section', 'id' => 'root', 'props' => ['title' => ['en' => 'Deal']],
            'children' => array_map(
                fn (string $f): array => ['type' => 'field', 'id' => "f-{$f}", 'binding' => ['field' => $f]],
                ['title', 'email', 'note', 'budget', 'age', 'appointment_date', 'priority', 'vip'],
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function detailSchema(): array
    {
        return [
            'type' => 'tabs', 'id' => 'root',
            'children' => [
                ['type' => 'section', 'id' => 'overview', 'props' => ['title' => ['en' => 'Overview']],
                    'children' => array_map(
                        fn (string $f): array => ['type' => 'field', 'id' => "d-{$f}", 'binding' => ['field' => $f]],
                        ['title', 'email', 'priority', 'budget'],
                    ),
                ],
                ['type' => 'section', 'id' => 'details', 'props' => ['title' => ['en' => 'Details']],
                    'children' => array_map(
                        fn (string $f): array => ['type' => 'field', 'id' => "d-{$f}", 'binding' => ['field' => $f]],
                        ['note', 'age', 'appointment_date', 'vip'],
                    ),
                ],
            ],
        ];
    }
}
