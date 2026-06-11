<?php
// database/migrations/2026_05_28_000002_refactor_auth_add_credentials_to_ref_personnes.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute les colonnes nécessaires à l'authentification Laravel
 * directement dans ref_personnes.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ref_personnes', function (Blueprint $table) {
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