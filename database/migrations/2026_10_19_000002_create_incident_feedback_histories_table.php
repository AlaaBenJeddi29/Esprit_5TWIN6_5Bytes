<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historique chronologique des actions liées à une évaluation ou à une
     * réclamation : dépôt, prise en examen, réponse, décision, clôture et
     * réouverture effective de l'incident.
     */
    public function up(): void
    {
        Schema::create('incident_feedback_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_feedback_id')
                ->constrained('incident_feedbacks')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->string('ancien_statut', 20)->nullable();
            $table->string('nouveau_statut', 20)->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['incident_feedback_id', 'created_at'], 'feedback_history_chronological_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_feedback_histories');
    }
};
