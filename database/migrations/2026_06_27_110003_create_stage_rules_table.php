<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $table->string('rule');   // require_fields | require_comment | cooldown | allow_backward
            $table->json('value');    // rule arg: [keys] | true | seconds | false
            $table->timestamps();

            $table->index(['tenant_id', 'stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_rules');
    }
};
