<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration additive : ajoute la colonne `role` à `users`.
 *
 * - Compatible avec les utilisateurs existants : valeur par défaut `citoyen`.
 * - Le rôle est stocké en string et casté côté Eloquent vers App\Enums\UserRole.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)
                ->default(UserRole::Citoyen->value)
                ->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
