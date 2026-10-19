<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_similarities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->foreignId('similar_incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->default(0);
            $table->string('method', 20)->default('local');
            $table->text('ai_justification')->nullable();
            $table->string('status', 30)->default('suggeree');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->unique(['incident_id', 'similar_incident_id'], 'incident_similarities_pair_unique');
            $table->index(['incident_id', 'status']);
            $table->index('similar_incident_id');
        });

        DB::statement(
            'ALTER TABLE incident_similarities '
            .'ADD CONSTRAINT incident_similarities_not_self CHECK (incident_id <> similar_incident_id)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_similarities');
    }
};
