<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_levels', function (Blueprint $table) {
            $table->id();
            $table->string('nom_source', 150);
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->string('type_source', 30)->default('barrage');
            $table->decimal('niveau', 5, 2);
            $table->string('unite', 10)->default('%');
            $table->decimal('seuil_alerte', 5, 2)->default(30);
            $table->decimal('seuil_critique', 5, 2)->default(15);
            $table->dateTime('date_mesure');
            $table->timestamps();

            $table->index(['zone_id', 'date_mesure']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_levels');
    }
};
