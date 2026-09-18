<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nutrition_entries', function (Blueprint $table): void {
            // pending = awaiting AI estimation, done = estimated, failed = estimation failed.
            $table->string('status', 12)->default('done')->after('carbs_g');
        });
    }

    public function down(): void
    {
        Schema::table('nutrition_entries', function (Blueprint $table): void {
            $table->dropColumn('status');
        });
    }
};
