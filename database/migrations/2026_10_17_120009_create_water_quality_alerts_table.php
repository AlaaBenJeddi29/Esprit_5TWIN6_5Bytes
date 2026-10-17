<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_quality_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->foreignId('sample_id')->nullable()->constrained('water_quality_samples')->nullOnDelete();
            $table->string('parametre', 50)->nullable();
            $table->string('niveau', 20)->default('avertissement')->index();
            $table->text('message');
            $table->string('statut', 20)->default('active')->index();
            $table->timestamp('resolue_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_quality_alerts');
    }
};
