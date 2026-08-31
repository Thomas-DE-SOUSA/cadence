<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('muscu_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 64)->unique();
            $table->string('goal', 24)->default('GENERAL');
            $table->string('level', 24)->default('INTERMEDIATE');
            $table->float('bodyweight_kg')->nullable();
            $table->unsignedTinyInteger('weekly_frequency')->default(3);
            $table->string('split', 24)->default('FREE');
            $table->string('equipment', 24)->default('FULL_GYM');
            $table->json('priorities');
            $table->json('limitations');
            $table->string('note', 500)->default('');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('muscu_profiles');
    }
};
