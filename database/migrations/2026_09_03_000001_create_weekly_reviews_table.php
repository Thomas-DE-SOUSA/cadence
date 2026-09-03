<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 64);
            $table->string('week_start', 10); // Y-m-d, the Monday of the reviewed week
            $table->json('messages');
            $table->unsignedInteger('version');
            $table->timestamps();

            // One review thread per week per tenant; the lookup path.
            $table->unique(['tenant_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_reviews');
    }
};
