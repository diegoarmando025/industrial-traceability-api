<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('boxes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('part_number', 80);
            $table->string('serial_number', 80);
            $table->string('status', 20)->default('pending');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });

        Schema::create('box_scans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('box_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('scan_type', 30);
            $table->string('scanned_value', 255);
            $table->boolean('accepted');
            $table->string('reason', 120)->nullable();
            $table->timestamp('created_at');
            $table->index(['box_id', 'scan_type', 'id']);
        });

        Schema::create('box_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('box_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('event_type', 40);
            $table->json('details')->nullable();
            $table->timestamp('created_at');
            $table->index(['box_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('box_events');
        Schema::dropIfExists('box_scans');
        Schema::dropIfExists('boxes');
    }
};
