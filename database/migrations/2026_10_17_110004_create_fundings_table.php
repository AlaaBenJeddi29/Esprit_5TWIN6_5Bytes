<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fundings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->enum('source', ['municipal', 'régional', 'fédéral', 'européen', 'privé', 'don']);
            $table->foreignId('donateur_id')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('montant', 15, 2);
            $table->date('date_versement');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fundings');
    }
};
