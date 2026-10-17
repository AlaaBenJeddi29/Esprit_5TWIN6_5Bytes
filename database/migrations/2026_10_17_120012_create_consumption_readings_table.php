<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumption_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->date('periode');
            $table->decimal('consommation_m3', 15, 2);
            $table->unsignedInteger('nombre_abonnes')->nullable();
            $table->timestamps();

            $table->unique(['zone_id', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumption_readings');
    }
};
