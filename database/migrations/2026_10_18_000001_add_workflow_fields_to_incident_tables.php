<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Champs additifs nécessaires au workflow strict :
     * - incidents.rejection_reason : motif obligatoire lors d'un rejet ;
     * - incident_status_histories.resolution_note / rejection_reason :
     *   snapshots conservés lors des réouvertures (le compte rendu ou le
     *   motif est retiré de l'incident mais reste traçable dans l'historique).
     */
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('resolution_note');
        });

        Schema::table('incident_status_histories', function (Blueprint $table) {
            $table->text('resolution_note')->nullable()->after('comment');
            $table->text('rejection_reason')->nullable()->after('resolution_note');
        });
    }

    public function down(): void
    {
        Schema::table('incident_status_histories', function (Blueprint $table) {
            $table->dropColumn(['resolution_note', 'rejection_reason']);
        });

        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });
    }
};
