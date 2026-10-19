<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumption_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->date('forecast_month');
            $table->decimal('prediction_m3', 15, 2);
            $table->decimal('lower_bound', 15, 2);
            $table->decimal('upper_bound', 15, 2);
            $table->enum('model_used', ['linear', 'holt_winters', 'seasonal'])->default('holt_winters');
            $table->decimal('mae', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['zone_id', 'forecast_month']);
            $table->index('forecast_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumption_forecasts');
    }
};
