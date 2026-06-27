<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipelines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_type_id')->constrained()->cascadeOnDelete();
            $table->string('key');                 // 'sales' | 'clinical' | ...
            $table->json('label');                 // locale-keyed display label
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'entity_type_id', 'key']);
            $table->index(['tenant_id', 'entity_type_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipelines');
    }
};
