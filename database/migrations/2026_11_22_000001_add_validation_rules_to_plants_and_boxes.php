<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('plants', function (Blueprint $table): void {
            $table->json('validation_rules')->nullable();
        });

        Schema::table('boxes', function (Blueprint $table): void {
            $table->json('validation_rules')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('boxes', fn (Blueprint $table) => $table->dropColumn('validation_rules'));
        Schema::table('plants', fn (Blueprint $table) => $table->dropColumn('validation_rules'));
    }
};
