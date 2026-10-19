<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Périmètre qualité des gestionnaires.
 *
 * Table dédiée au module Qualité : elle n'altère ni les rôles ni les
 * permissions existants. Un gestionnaire ne voit que les zones qui lui
 * sont explicitement affectées ; un administrateur voit toutes les zones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_zone_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_zone_assignments');
    }
};
