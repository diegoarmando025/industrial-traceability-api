<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plants', function (Blueprint $table): void {
            $table->unsignedInteger('validation_rules_version')->default(0);
        });

        Schema::create('plant_validation_rule_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->json('previous_rules');
            $table->json('new_rules');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['plant_id', 'version']);
            $table->index(['plant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plant_validation_rule_changes');
        Schema::table('plants', function (Blueprint $table): void {
            $table->dropColumn('validation_rules_version');
        });
    }
};
