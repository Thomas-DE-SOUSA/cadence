<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weight_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 64);
            $table->string('logged_date', 10);
            $table->string('moment', 16);
            $table->float('weight_kg');
            $table->string('note', 255)->default('');
            $table->timestamps();

            $table->unique(['tenant_id', 'logged_date', 'moment']);
            $table->index(['tenant_id', 'logged_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weight_entries');
    }
};
