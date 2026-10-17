<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_quality_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->constrained('water_quality_samples')->cascadeOnDelete();
            $table->string('parametre', 50);
            $table->decimal('valeur', 12, 4);
            $table->string('unite', 20);
            $table->decimal('seuil_min', 12, 4)->nullable();
            $table->decimal('seuil_max', 12, 4)->nullable();
            $table->boolean('est_conforme')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_quality_parameters');
    }
};
