<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paiement Stripe des dons citoyens (mode test uniquement).
 *
 * Les financements existants (subventions, fonds propres…) restent sans statut
 * de paiement : `statut_paiement` NULL signifie « financement confirmé », comme
 * avant cette migration. Seuls les dons en ligne portent un statut explicite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fundings', function (Blueprint $table) {
            $table->string('statut_paiement', 20)->nullable()->after('date_versement')->index();
            $table->string('stripe_session_id')->nullable()->unique()->after('statut_paiement');
        });
    }

    public function down(): void
    {
        Schema::table('fundings', function (Blueprint $table) {
            $table->dropUnique(['stripe_session_id']);
            $table->dropColumn(['stripe_session_id', 'statut_paiement']);
        });
    }
};
