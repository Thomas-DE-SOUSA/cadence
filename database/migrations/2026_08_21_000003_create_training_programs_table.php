<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_programs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 64);
            $table->string('name', 160);
            $table->string('goal', 500);
            $table->string('target_race_name', 160);
            $table->string('target_race_date', 40)->nullable();
            $table->string('start_date', 40);
            $table->string('end_date', 40)->nullable();
            $table->string('priority', 1);
            $table->string('status', 16);
            $table->json('objectives');
            $table->json('assigned_activity_ids');
            $table->unsignedInteger('version');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_programs');
    }
};
