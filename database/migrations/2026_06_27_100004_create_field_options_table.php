<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_definition_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->json('label');
            $table->string('color')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['field_definition_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_options');
    }
};
