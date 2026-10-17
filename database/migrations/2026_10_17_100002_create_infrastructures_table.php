<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gestion 1 — Infrastructures (canalisations, réservoirs, etc.).
 * Schéma repris des migrations finales exécutées du dépôt de référence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infrastructures', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('type');
            $table->string('materiau')->nullable();
            $table->date('date_installation')->nullable();
            $table->decimal('capacite', 12, 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('statut')->default('actif');
            $table->decimal('score_risque', 5, 2)->default(0);
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infrastructures');
    }
};
