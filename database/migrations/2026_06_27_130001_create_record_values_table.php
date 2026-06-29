<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EAV projection (ARCHITECTURE §3): a narrow, typed, indexed value row per (record, field) for fields
 * flagged filterable/sortable. records.data (JSON) remains the source of truth; this table is a
 * REBUILDABLE projection that makes custom-field filtering/sorting fast. One row per record+field
 * (unique), so sync is idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_definition_id')->constrained()->cascadeOnDelete();

            // Typed value slots — exactly one is populated per row, chosen by the field's type.
            $table->string('value_string')->nullable();
            $table->double('value_number')->nullable();
            $table->dateTime('value_date')->nullable();
            $table->boolean('value_bool')->nullable();
            $table->unsignedBigInteger('value_option_id')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'record_id', 'field_definition_id'], 'record_values_unique');
            $table->index(['tenant_id', 'field_definition_id', 'value_string'], 'rv_string_idx');
            $table->index(['tenant_id', 'field_definition_id', 'value_number'], 'rv_number_idx');
            $table->index(['tenant_id', 'field_definition_id', 'value_date'], 'rv_date_idx');
            $table->index(['tenant_id', 'field_definition_id', 'value_bool'], 'rv_bool_idx');
            $table->index(['tenant_id', 'field_definition_id', 'value_option_id'], 'rv_option_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_values');
    }
};
