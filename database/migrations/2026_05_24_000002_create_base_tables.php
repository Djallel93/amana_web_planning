<?php
// database/migrations/2026_05_24_000002_create_base_tables.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration : tables de référence (rôles, tâches, personnes)
 *
 * ref_roles dépend de ref_applications (id_application) — doit donc
 * s'exécuter après elle. Ne dépend d'aucune autre table applicative.
 */
return new class extends Migration {
    public function up(): void
    {
        // ── ref_roles ──────────────────────────────────────────────────────
        Schema::create('ref_roles', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('code', 50)
                ->comment('Identifiant technique : admin, gestionnaire, membre, benevole...');
            $table->string('libelle', 100);
            $table->unsignedTinyInteger('id_application')
                ->comment('Application à laquelle ce rôle appartient');

            // Unique par (code, id_application), et non par code seul : un
            // même code (ex. "admin") doit pouvoir exister pour plusieurs
            // applications AMANA partageant cette base (planning, familles...).
            $table->unique(['code', 'id_application'], 'uq_roles_code_app');

            $table->foreign('id_application')
                ->references('id')->on('ref_applications')
                ->onDelete('cascade')->onUpdate('cascade');
        });

        // ── ref_taches ─────────────────────────────────────────────────────
        Schema::create('ref_taches', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('code', 50)->unique()
                ->comment('entree, mektaba, salle, amana_food, cours, rappel_sandwich, assistance_amana_food, annonce_cours, message_bot, annulation_cours');
            $table->string('libelle', 100);
            $table->string('description', 250)
                ->comment('Résumé court affiché dans l\'app (inscription, disponibilités)');
            $table->text('description_calendrier')->nullable()
                ->comment('Texte envoyé dans le body de l\'événement Google Calendar — distinct de `description`');
            $table->boolean('actif')->default(true)
                ->comment('FALSE = archivée, exclue des nouveaux plannings');
        });

        // ── ref_personnes ──────────────────────────────────────────────────
        Schema::create('ref_personnes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('email', 255)->unique();
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
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_personnes_roles');
        Schema::dropIfExists('ref_personnes');
        Schema::dropIfExists('ref_taches');
        Schema::dropIfExists('ref_roles');
    }
};
