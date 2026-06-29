<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EntityType;
use App\Models\Layout;
use App\Models\Pipeline;
use App\Models\Tenant;
use App\Support\Tenancy\TenantManager;
use Illuminate\Database\Seeder;

/**
 * Phase 1 demo: a 'deal' entity type with 8 fields + form/detail layouts, for the Nokta tenant.
 * Phase 2 adds a 'sales' pipeline (with stages + transition rules) and card/board layouts.
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

        // Phase 2: a sales pipeline + stages (with rules) and card/board layouts.
        $this->seedPipeline($tenant->id, $deal->id);
        $this->upsertLayout($tenant->id, $deal->id, 'card', 'deal.card', $this->cardSchema());
        $this->upsertLayout($tenant->id, $deal->id, 'board', 'deal.board', $this->boardSchema());

        // Phase 3: a tenant nav layout — each item gated by a ui.nav.* permission and resolved by the
        // frontend catch-all route + view registry (slug → viewType → page component).
        $this->upsertLayout($tenant->id, $deal->id, 'nav', 'main', $this->navSchema());

        $manager->forget();
    }

    private function seedPipeline(int $tenantId, int $entityTypeId): void
    {
        /** @var Pipeline $pipeline */
        $pipeline = Pipeline::query()->updateOrCreate(
            ['entity_type_id' => $entityTypeId, 'key' => 'sales'],
            ['tenant_id' => $tenantId, 'label' => ['en' => 'Sales'], 'position' => 0],
        );

        foreach ($this->stages() as $position => $stage) {
            $model = $pipeline->stages()->updateOrCreate(
                ['key' => $stage['key']],
                [
                    'tenant_id' => $tenantId,
                    'label' => $stage['label'],
                    'color' => $stage['color'] ?? null,
                    'position' => $position,
                    'is_initial' => $stage['is_initial'] ?? false,
                    'is_won' => $stage['is_won'] ?? false,
                    'is_lost' => $stage['is_lost'] ?? false,
                ],
            );

            $model->rules()->delete();
            /** @var list<array{rule: string, value: mixed}> $rules */
            $rules = $stage['rules'] ?? [];
            foreach ($rules as $rule) {
                $model->rules()->create(['tenant_id' => $tenantId, 'rule' => $rule['rule'], 'value' => $rule['value']]);
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function stages(): array
    {
        return [
            ['key' => 'new', 'label' => ['en' => 'New'], 'color' => '#6b7280', 'is_initial' => true],
            ['key' => 'qualified', 'label' => ['en' => 'Qualified'], 'color' => '#2e74b5',
                'rules' => [['rule' => 'allow_backward', 'value' => true]]],
            ['key' => 'won', 'label' => ['en' => 'Won'], 'color' => '#1f9d55', 'is_won' => true,
                'rules' => [
                    ['rule' => 'require_fields', 'value' => ['budget']],
                    ['rule' => 'require_comment', 'value' => true],
                ]],
            ['key' => 'lost', 'label' => ['en' => 'Lost'], 'color' => '#dd3636', 'is_lost' => true],
        ];
    }

    /** @return array<string, mixed> */
    private function cardSchema(): array
    {
        return [
            'type' => 'card', 'id' => 'card-root',
            'children' => array_map(
                fn (string $f): array => ['type' => 'card-slot', 'id' => "c-{$f}", 'binding' => ['field' => $f]],
                ['title', 'priority', 'budget'],
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function boardSchema(): array
    {
        return [
            'type' => 'board', 'id' => 'board-root',
            'props' => ['pipeline' => 'sales', 'cardLayoutKey' => 'deal.card'],
        ];
    }

    /** @return array<string, mixed> */
    private function navSchema(): array
    {
        return [
            'type' => 'nav', 'id' => 'nav-root',
            'children' => [
                [
                    'type' => 'nav-item', 'id' => 'nav-pipeline', 'permission' => ['ui' => 'ui.nav.boards'],
                    'props' => [
                        'slug' => 'pipeline', 'label' => ['en' => 'Pipeline'], 'icon' => 'pi-th-large',
                        'viewType' => 'kanban-board', 'entityType' => 'deal', 'pipeline' => 'sales', 'layoutKey' => 'deal.board',
                    ],
                ],
                [
                    'type' => 'nav-item', 'id' => 'nav-leads', 'permission' => ['ui' => 'ui.nav.list'],
                    'props' => [
                        'slug' => 'leads', 'label' => ['en' => 'Leads'], 'icon' => 'pi-list',
                        'viewType' => 'list', 'entityType' => 'deal', 'layoutKey' => 'deal.detail',
                    ],
                ],
            ],
        ];
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
