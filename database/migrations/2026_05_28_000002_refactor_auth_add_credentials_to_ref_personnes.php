<?php
// database/migrations/2026_05_28_000002_refactor_auth_add_credentials_to_ref_personnes.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Étape 2 du refactoring auth.
 *
 * Ajoute les colonnes nécessaires à l'authentification Laravel
 * directement dans ref_personnes, qui devient le modèle User de l'app.
 *
 * Colonnes ajoutées :
 *   - password          : mot de passe hashé (nullable au début car les membres
 *                         existants n'en ont pas encore — ils le créeront via email)
 *   - remember_token    : requis par Laravel pour "se souvenir de moi"
 *   - email_verified_at : requis par Laravel pour la vérification d'email
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ref_personnes', function (Blueprint $table) {
            // Nullable car les membres existants créeront leur mdp via invitation
            $table->string('password')->nullable()->after('email');
            $table->rememberToken()->after('password');
            $table->timestamp('email_verified_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('ref_personnes', function (Blueprint $table) {
            $table->dropColumn(['password', 'remember_token', 'email_verified_at']);
        });
    }
};
