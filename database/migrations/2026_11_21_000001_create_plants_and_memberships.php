<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plants', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 120);
            $table->timestamps();
        });

        Schema::create('plant_user', function (Blueprint $table): void {
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->primary(['plant_id', 'user_id']);
        });

        // Nullable for existing demo data. Unassigned legacy boxes remain inaccessible.
        // A subsequent controlled data migration can assign them to a plant.
        Schema::table('boxes', function (Blueprint $table): void {
            $table->foreignId('plant_id')->nullable()->constrained()->restrictOnDelete();
            $table->index(['plant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('boxes', function (Blueprint $table): void {
            $table->dropForeign(['plant_id']);
            $table->dropIndex(['plant_id', 'status']);
            $table->dropColumn('plant_id');
        });

        Schema::dropIfExists('plant_user');
        Schema::dropIfExists('plants');
    }
};
