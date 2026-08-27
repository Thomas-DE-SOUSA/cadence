<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wellness_check_ins', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 64);
            $table->string('check_date', 10);
            $table->unsignedTinyInteger('sleep');
            $table->unsignedTinyInteger('energy');
            $table->unsignedTinyInteger('legs');
            $table->unsignedTinyInteger('motivation');
            $table->unsignedTinyInteger('pain_level')->default(0);
            $table->string('pain_location', 120)->default('');
            $table->string('note', 500)->default('');
            $table->timestamps();

            $table->unique(['tenant_id', 'check_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wellness_check_ins');
    }
};
