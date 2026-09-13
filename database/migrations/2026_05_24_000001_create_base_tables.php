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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── ref_applications ──────────────────────────────────────────────
        // Référentiel de toutes les applications AMANA partageant cette base
        // de données. Chaque rôle dans ref_roles sera lié à une application
        // spécifique, permettant à une même personne d'avoir des rôles
        // différents selon l'app. Exemples : admin sur planning, livreur sur
        // livraisons, tresorier sur tirelire.
        Schema::create('ref_applications', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('code', 50)->unique()
                ->comment('Identifiant technique : planning, livraisons, tirelire, familles, benevoles');
            $table->string('libelle', 100)
                ->comment('Nom lisible : AMANA Planning, Livraisons, etc.');
            $table->boolean('actif')->default(true);
        });

        // ── ref_personnes ─────────────────────────────────────────────────
        Schema::create('ref_personnes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('email', 255)->unique();

            // Colonnes d'authentification Laravel (refactor auth, étape 2).
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();

            $table->string('telephone', 20)->nullable();
            $table->date('date_debut_planning')->nullable()
                ->comment('NULL si la personne n\'est pas encore dans la rotation');
            $table->enum('statut', ['En attente', 'Validé', 'Suspendu', 'Archivé'])
                ->default('En attente');
            $table->timestamp('derniere_maj')->useCurrent()->useCurrentOnUpdate();
        });

        // ── ref_roles ──────────────────────────────────────────────────────
        Schema::create('ref_roles', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('code', 50)->unique();
            $table->string('libelle', 100);

            // Refactor auth, étape 4 : chaque rôle appartient à une application.
            $table->unsignedTinyInteger('id_application')
                ->comment('Application à laquelle ce rôle appartient');

            $table->foreign('id_application')
                ->references('id')->on('ref_applications')
                ->onDelete('cascade')->onUpdate('cascade');
        });

        // ── ref_personnes_roles ────────────────────────────────────────────
        Schema::create('ref_personnes_roles', function (Blueprint $table) {
            $table->unsignedInteger('id_personne');
            $table->unsignedTinyInteger('id_role');
            $table->date('date_attribution')->default(DB::raw('(curdate())'));

            $table->primary(['id_personne', 'id_role']);

            $table->foreign('id_personne')
                ->references('id')->on('ref_personnes')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_role')
                ->references('id')->on('ref_roles')
                ->onDelete('restrict')->onUpdate('cascade');
        });

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

        // Insère l'application planning d'emblée (comportement préservé
        // depuis l'ancienne migration refactor_auth étape 3).
        DB::table('ref_applications')->insert([
            'code'    => 'planning',
            'libelle' => 'AMANA Planning',
            'actif'   => true,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('ref_personnes_roles');
        Schema::dropIfExists('ref_roles');
        Schema::dropIfExists('ref_personnes');
        Schema::dropIfExists('ref_applications');
    }
};
