<?php
// database/migrations/2026_05_24_000001_create_base_tables.php
//
// Consolidated from (squash, see git history for the originals):
//   - 2026_05_24_000001_create_base_tables.php (ref_roles, ref_personnes, ref_personnes_roles)
//   - 2026_05_28_000002_refactor_auth_add_credentials_to_ref_personnes.php
//   - 2026_05_28_000003_refactor_auth_create_ref_applications.php
//   - 2026_05_28_000004_refactor_auth_add_application_to_ref_roles.php
//   - 2026_05_28_000005_refactor_auth_create_password_reset_tokens.php
//   - 2026_06_18_000001_create_sessions_table.php
//
// Core/unprefixed tables + anything auth-related — created first, no
// dependencies on other domain files (ref_/plan_ tables depend on these,
// not the other way round).
//
// Note (found during the squash, not fixed here): ref_taches's ORIGINAL
// version briefly lived inline in this file too, before being split out
// into its own migration — see create_ref_tables.php for that table and
// why the split-out definition is the one that survived.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── ref_applications / ref_personnes / ref_roles / ref_personnes_roles
        // Volontairement ABSENTES ici : ces tables vivent dans amana_commun
        // et appartiennent au paquet amana/shared (migrations exécutées via
        // `php artisan amana:migrate-shared`). Elles étaient créées ici
        // avant l'extraction du paquet partagé ; les copies locales qui en
        // résultaient étaient vides et captaient silencieusement toute
        // requête non explicitement routée vers la connexion 'commun'.
        // Supprimées des bases existantes par
        // 2026_09_16_000001_drop_shadow_commun_tables.php.
        // ─────────────────────────────────────────────────────────────────

        // ── password_reset_tokens ─────────────────────────────────────────
        // Nécessaire pour le système de réinitialisation de mot de passe de
        // Laravel (forgot password / first login par lien email). La colonne
        // email correspond à ref_personnes.email ; Laravel gère lui-même la
        // correspondance via le provider 'personnes' défini dans
        // config/auth.php — pas besoin de FK explicite.
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary()
                ->comment('Email de la personne — correspond à ref_personnes.email');
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // ── sessions ──────────────────────────────────────────────────────
        // Pour le driver SESSION_DRIVER=database, utilisé en production
        // (IONOS) pour stocker les sessions côté serveur. Structure
        // identique au stub Laravel 11/13 officiel.
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // L'enregistrement de l'application 'planning' dans ref_applications
        // se fait désormais via PlanningApplicationSeeder (base amana_commun) —
        // une migration de planning ne doit jamais écrire dans la base
        // partagée (voir AmanaSharedServiceProvider, SÉCURITÉ CRITIQUE).
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
    }
};
