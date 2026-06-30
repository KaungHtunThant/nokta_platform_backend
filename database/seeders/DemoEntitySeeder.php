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
            ['label' => ['en' => 'Deal', 'ar' => 'صفقة'], 'icon' => 'pi-briefcase', 'supports_pipeline' => true,
                'config' => ['title_field' => 'title']]
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

        // Phase 6: two more entity types modelled entirely through config (no new tables/components),
        // plus a deal→contact relation. Proves the generalization holds for a second & third entity.
        $this->seedContact($tenant->id);
        $this->seedPatient($tenant->id);

        // Phase 3: a tenant nav layout — each item gated by a ui.nav.* permission and resolved by the
        // frontend catch-all route + view registry (slug → viewType → page component).
        $this->upsertLayout($tenant->id, $deal->id, 'nav', 'main', $this->navSchema());

        $manager->forget();
    }

    /**
     * Phase 6 — a 'contact' entity type (no pipeline) authored purely as config. Its detail surface
     * lists the deals linked to it via record_links (the deal→contact relation).
     */
    private function seedContact(int $tenantId): void
    {
        $contact = EntityType::query()->firstOrCreate(
            ['key' => 'contact'],
            ['label' => ['en' => 'Contact', 'ar' => 'جهة اتصال'], 'icon' => 'pi-user', 'supports_pipeline' => false,
                'config' => ['title_field' => 'name']]
        );

        $fields = [
            ['key' => 'name', 'type' => 'text', 'label' => ['en' => 'Name'], 'validation' => ['required' => true], 'is_sortable' => true, 'is_reportable' => true],
            ['key' => 'email', 'type' => 'email', 'label' => ['en' => 'Email'], 'is_reportable' => true],
            ['key' => 'phone', 'type' => 'phone', 'label' => ['en' => 'Phone']],
            ['key' => 'company', 'type' => 'text', 'label' => ['en' => 'Company'], 'is_filterable' => true],
        ];
        foreach ($fields as $position => $field) {
            $contact->fieldDefinitions()->updateOrCreate(
                ['key' => $field['key']],
                array_merge($field, ['position' => $position, 'storage_strategy' => 'json'])
            );
        }

        $this->upsertLayout($tenantId, $contact->id, 'form', 'contact.form', [
            'type' => 'section', 'id' => 'root', 'props' => ['title' => ['en' => 'Contact']],
            'children' => $this->fieldNodes(['name', 'email', 'phone', 'company']),
        ]);
        $this->upsertLayout($tenantId, $contact->id, 'detail', 'contact.detail', [
            'type' => 'tabs', 'id' => 'root',
            'children' => [
                ['type' => 'section', 'id' => 'overview', 'props' => ['title' => ['en' => 'Overview']],
                    'children' => $this->fieldNodes(['name', 'email', 'phone', 'company'])],
                // The deals linked to this contact (relation_key = the deal's 'contact' field). One node
                // type renders related records for any entity — no contact-specific component.
                ['type' => 'section', 'id' => 'deals', 'props' => ['title' => ['en' => 'Deals']],
                    'children' => [[
                        'type' => 'related-records', 'id' => 'rel-deals',
                        'props' => ['relationKey' => 'contact', 'title' => ['en' => 'Related deals']],
                    ]],
                ],
            ],
        ]);
    }

    /**
     * Phase 6 — a 'patient' entity type: a second config-only entity (proving "zero new code" twice),
     * with its own relation back to a contact.
     */
    private function seedPatient(int $tenantId): void
    {
        $patient = EntityType::query()->firstOrCreate(
            ['key' => 'patient'],
            ['label' => ['en' => 'Patient', 'ar' => 'مريض'], 'icon' => 'pi-heart', 'supports_pipeline' => false,
                'config' => ['title_field' => 'full_name']]
        );

        $fields = [
            ['key' => 'full_name', 'type' => 'text', 'label' => ['en' => 'Full name'], 'validation' => ['required' => true], 'is_sortable' => true, 'is_reportable' => true],
            ['key' => 'mrn', 'type' => 'text', 'label' => ['en' => 'MRN'], 'is_filterable' => true, 'is_reportable' => true],
            ['key' => 'date_of_birth', 'type' => 'date', 'label' => ['en' => 'Date of birth'], 'is_filterable' => true],
            ['key' => 'contact', 'type' => 'relation', 'label' => ['en' => 'Primary contact'],
                'ui' => ['target_entity_type' => 'contact', 'canonical_column' => 'contact_id']],
        ];
        foreach ($fields as $position => $field) {
            $patient->fieldDefinitions()->updateOrCreate(
                ['key' => $field['key']],
                array_merge($field, ['position' => $position, 'storage_strategy' => 'json'])
            );
        }

        $this->upsertLayout($tenantId, $patient->id, 'form', 'patient.form', [
            'type' => 'section', 'id' => 'root', 'props' => ['title' => ['en' => 'Patient']],
            'children' => $this->fieldNodes(['full_name', 'mrn', 'date_of_birth', 'contact']),
        ]);
        $this->upsertLayout($tenantId, $patient->id, 'detail', 'patient.detail', [
            'type' => 'tabs', 'id' => 'root',
            'children' => [
                ['type' => 'section', 'id' => 'overview', 'props' => ['title' => ['en' => 'Overview']],
                    'children' => $this->fieldNodes(['full_name', 'mrn', 'date_of_birth', 'contact'])],
            ],
        ]);
    }

    /**
     * Field nodes for a surface schema (shared by the Phase 6 entities).
     *
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    private function fieldNodes(array $keys): array
    {
        return array_map(
            fn (string $f): array => ['type' => 'field', 'id' => "f-{$f}", 'binding' => ['field' => $f]],
            $keys,
        );
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
                // Phase 6 — list views for the new entity types, each rendered from config alone.
                [
                    'type' => 'nav-item', 'id' => 'nav-contacts', 'permission' => ['ui' => 'ui.nav.contacts'],
                    'props' => [
                        'slug' => 'contacts', 'label' => ['en' => 'Contacts'], 'icon' => 'pi-user',
                        'viewType' => 'list', 'entityType' => 'contact', 'layoutKey' => 'contact.detail',
                    ],
                ],
                [
                    'type' => 'nav-item', 'id' => 'nav-patients', 'permission' => ['ui' => 'ui.nav.patients'],
                    'props' => [
                        'slug' => 'patients', 'label' => ['en' => 'Patients'], 'icon' => 'pi-heart',
                        'viewType' => 'list', 'entityType' => 'patient', 'layoutKey' => 'patient.detail',
                    ],
                ],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function fields(): array
    {
        return [
            ['key' => 'title', 'type' => 'text', 'label' => ['en' => 'Title'], 'validation' => ['required' => true], 'is_sortable' => true, 'is_reportable' => true],
            ['key' => 'note', 'type' => 'textarea', 'label' => ['en' => 'Note']],
            ['key' => 'email', 'type' => 'email', 'label' => ['en' => 'Email'], 'is_reportable' => true],
            ['key' => 'budget', 'type' => 'money', 'label' => ['en' => 'Budget'], 'is_filterable' => true, 'is_sortable' => true, 'is_reportable' => true],
            ['key' => 'age', 'type' => 'number', 'label' => ['en' => 'Age']],
            ['key' => 'appointment_date', 'type' => 'date', 'label' => ['en' => 'Appointment date'], 'is_filterable' => true],
            ['key' => 'vip', 'type' => 'bool', 'label' => ['en' => 'VIP'], 'is_filterable' => true],
            ['key' => 'priority', 'type' => 'select', 'label' => ['en' => 'Priority'], 'is_filterable' => true, 'is_sortable' => true,
                'ui' => ['options' => [
                    ['key' => 'low', 'label' => ['en' => 'Low']],
                    ['key' => 'medium', 'label' => ['en' => 'Medium']],
                    ['key' => 'high', 'label' => ['en' => 'High']],
                ]],
            ],
            // Phase 6: the canonical deal→contact relation. Its JSON value is the contact's record id,
            // mirrored into record_links + the locked contact_id column by RecordWriteService.
            ['key' => 'contact', 'type' => 'relation', 'label' => ['en' => 'Contact'],
                'ui' => ['target_entity_type' => 'contact', 'canonical_column' => 'contact_id']],
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
                ['title', 'email', 'note', 'budget', 'age', 'appointment_date', 'priority', 'vip', 'contact'],
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
                        ['title', 'email', 'priority', 'budget', 'contact'],
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
