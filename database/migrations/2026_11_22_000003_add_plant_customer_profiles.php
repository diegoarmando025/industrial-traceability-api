<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_customer_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plant_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->json('validation_rules');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['plant_id', 'code']);
            $table->unique(['id', 'plant_id']);
        });

        Schema::table('boxes', function (Blueprint $table): void {
            $table->unsignedBigInteger('customer_profile_id')->nullable()->after('plant_id');
            $table->foreign(['customer_profile_id', 'plant_id'], 'boxes_profile_plant_fk')
                ->references(['id', 'plant_id'])->on('plant_customer_profiles')->restrictOnDelete();
            $table->index(['customer_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('boxes', function (Blueprint $table): void {
            $table->dropForeign('boxes_profile_plant_fk');
            $table->dropIndex(['customer_profile_id', 'status']);
            $table->dropColumn('customer_profile_id');
        });
        Schema::dropIfExists('plant_customer_profiles');
    }
};
