<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 64)->nullable(); // null = global library shared by everyone
            $table->string('name');
            $table->string('primary_muscle', 32);
            $table->string('equipment', 32);
            $table->boolean('is_custom')->default(false);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('primary_muscle');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
