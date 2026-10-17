<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gestion 1 — Maintenances d'infrastructures.
 * Schéma repris des migrations finales exécutées du dépôt de référence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('infrastructure_id')->constrained('infrastructures')->cascadeOnDelete();
            $table->foreignId('technicien_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->text('description')->nullable();
            $table->date('date_intervention');
            $table->decimal('cout', 12, 2)->nullable();
            $table->string('statut')->default('planifiee');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenances');
    }
};
