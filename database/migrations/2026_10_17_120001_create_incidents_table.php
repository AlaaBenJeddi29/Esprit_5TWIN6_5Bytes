<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->foreignId('infrastructure_id')->nullable()->constrained('infrastructures')->nullOnDelete();
            $table->string('type', 30);
            $table->string('title', 180);
            $table->text('description');
            $table->string('address', 255)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('severity', 20)->default('moyenne');
            $table->string('status', 20)->default('nouveau')->index();
            $table->string('priority', 20)->default('normale');
            $table->string('ai_category', 30)->nullable();
            $table->decimal('ai_confidence', 5, 2)->nullable();
            $table->text('ai_summary')->nullable();
            $table->boolean('is_duplicate')->default(false);
            $table->foreignId('duplicate_of_id')->nullable()->constrained('incidents')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['zone_id', 'type']);
            $table->index('reported_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
