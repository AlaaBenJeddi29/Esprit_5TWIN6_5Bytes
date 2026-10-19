<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Évaluation initiale d'un incident traité et réclamation éventuelle.
     * Une seule ligne par couple (incident, citoyen) : l'évaluation initiale
     * et la réclamation associée partagent donc le même enregistrement.
     */
    public function up(): void
    {
        Schema::create('incident_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->boolean('resolution_confirmed')->default(false);
            $table->text('complaint_description')->nullable();
            $table->boolean('reopen_requested')->default(false);
            $table->string('complaint_status', 20)->nullable()->index();
            $table->string('complaint_decision', 20)->nullable()->index();
            $table->text('manager_response')->nullable();
            $table->text('decision_reason')->nullable();
            $table->text('reopen_decision_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->timestamps();

            $table->unique(['incident_id', 'user_id']);
            $table->index(['created_at']);
            $table->index('rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_feedbacks');
    }
};
