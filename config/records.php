<?php

declare(strict_types=1);

return [
    /*
     | Performance ceiling (Phase 7): the max number of EAV-projected (is_filterable) field definitions
     | allowed per tenant. Filterable fields each cost an indexed record_values projection, so this caps
     | the write/projection overhead. Overridable per tenant via tenant_settings key
     | `limits => {filterable_fields: N}`.
     */
    'max_filterable_fields' => (int) env('MAX_FILTERABLE_FIELDS', 20),
];
