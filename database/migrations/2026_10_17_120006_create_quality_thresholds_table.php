<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_thresholds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->cascadeOnDelete();
            $table->string('parametre', 50);
            $table->string('unite', 20);
            $table->decimal('valeur_min', 12, 4)->nullable();
            $table->decimal('valeur_max', 12, 4)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['zone_id', 'parametre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_thresholds');
    }
};
