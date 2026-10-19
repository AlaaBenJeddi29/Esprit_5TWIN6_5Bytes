<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->string('type_alerte', 30); // 'qualite', 'restriction', 'coupure'
            $table->unsignedBigInteger('alert_id')->nullable(); // FK to source alert (WaterQualityAlert, Restriction, ScheduledCut)
            $table->string('titre');
            $table->text('description')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'zone_id']);
            $table->index(['type_alerte']);
            $table->index(['user_id', 'zone_id', 'type_alerte', 'alert_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_logs');
    }
};
