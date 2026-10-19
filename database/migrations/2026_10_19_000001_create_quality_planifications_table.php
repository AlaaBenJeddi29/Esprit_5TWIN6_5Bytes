<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planification des contrôles qualité.
 *
 * Une ligne représente une occurrence planifiée. Le statut n'est pas stocké :
 * il est déduit du lien vers un prélèvement et de la date prévue, ce qui
 * garantit qu'un contrôle ne peut jamais être « effectué » sans prélèvement.
 * La récurrence crée une nouvelle occurrence reliée à la précédente
 * (`parent_id`) afin de conserver tout l'historique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_planifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('water_quality_sample_id')->nullable()->unique()
                ->constrained('water_quality_samples')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()
                ->constrained('quality_planifications')->nullOnDelete();
            $table->date('date_prevue');
            $table->string('frequence', 20)->default('mensuelle');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['zone_id', 'date_prevue']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_planifications');
    }
};
