<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrition_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 64);
            $table->string('logged_date', 10);
            $table->string('meal', 16);
            $table->string('description', 255);
            $table->integer('kcal');
            $table->integer('protein_g');
            $table->integer('fat_g');
            $table->integer('carbs_g');
            $table->timestamps();

            $table->index(['tenant_id', 'logged_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_entries');
    }
};
