<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_quality_samples', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->foreignId('infrastructure_id')->nullable()->constrained('infrastructures')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('date_prelevement');
            $table->string('source', 80)->nullable();
            $table->string('statut', 20)->default('en_attente')->index();
            $table->decimal('score', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['zone_id', 'date_prelevement']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_quality_samples');
    }
};
