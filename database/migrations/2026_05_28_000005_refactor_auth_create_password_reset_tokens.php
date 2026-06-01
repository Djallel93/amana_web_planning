<?php
// database/migrations/2026_05_28_000005_refactor_auth_create_password_reset_tokens.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recrée la table password_reset_tokens.
 *
 * Cette table a été supprimée avec users dans la migration 000001.
 * Elle est nécessaire pour le système de réinitialisation de mot de passe
 * de Laravel (forgot password / first login par lien email).
 *
 * La colonne email correspond à ref_personnes.email.
 * Laravel gère lui-même la correspondance via le provider 'personnes'
 * défini dans config/auth.php — pas besoin de FK explicite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary()
                ->comment('Email de la personne — correspond à ref_personnes.email');
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
