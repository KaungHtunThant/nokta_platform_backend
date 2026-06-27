<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_type_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('type');                 // text, number, money, date, bool, select, ...
            $table->json('label');
            $table->json('help')->nullable();
            $table->json('validation')->nullable();  // {required, min, max, ...}
            $table->json('ui')->nullable();          // {widget, placeholder, width, ...}
            $table->json('default_value')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_translatable')->default(false);
            $table->integer('position')->default(0);

            // Storage strategy (Phase 1 uses 'json'; 'eav' projection added in Phase 4).
            $table->string('storage_strategy')->default('json'); // column | json | eav
            $table->string('storage_column')->nullable();
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_sortable')->default(false);
            $table->boolean('is_reportable')->default(false);

            $table->timestamps();

            $table->unique(['tenant_id', 'entity_type_id', 'key']);
            $table->index(['tenant_id', 'entity_type_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_definitions');
    }
};
