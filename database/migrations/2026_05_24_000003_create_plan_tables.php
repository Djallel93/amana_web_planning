<?php
// database/migrations/2026_05_24_000003_create_plan_tables.php
//
// Consolidated from (squash, see git history for the originals):
//   - 2026_05_24_000003_create_planning_tables.php
//   - 2026_06_18_000001_add_calendar_name_to_ref_evenements.php
//   - 2026_06_18_000002_create_plan_echanges_table.php
//   - 2026_07_01_000001_create_plan_bilans_quotidiens_table.php
//   - 2026_07_03_000001_create_evenements_calendriers_table.php
//   - 2026_07_16_000002_create_plan_calendrier_evenements_table.php
//   - 2026_07_17_000001... (ref_calendriers_google — see create_ref_tables.php instead)
//   - 2026_07_18_000002_create_plan_rappels_envoyes_table.php
//   - 2026_07_19_000001_add_google_calendar_tracking_to_plan_absences_table.php
//
// Deviation from the plain plan_ prefix convention: ref_evenements,
// ref_evenements_taches and ref_evenements_calendriers are included here
// rather than in create_ref_tables.php. They're `ref_`-prefixed but are
// planning/événement-domain tables through and through (FK'd from/to the
// plan_ tables below, historically always created together in this same
// file) rather than generic reference/lookup tables — grouping them with
// the plan_ tables keeps one cohesive, FK-ordered domain file instead of
// splitting tightly-coupled tables across two files.
//
// Second duplicate-definition bug found during this squash (not the one
// named going in, but the same species as ref_taches): this file's
// predecessor existed as TWO competing versions —
// 2026_05_24_000002_create_planning_tables.php (older: local FK from
// plan_absences/plan_creneaux_taches/plan_restrictions to ref_personnes,
// no `couleur` column on ref_evenements, ref_evenements_taches split into
// its own 2026_05_24_000002b file) and
// 2026_05_24_000003_create_planning_tables.php (newer: FK to ref_personnes
// removed because that table moved to the amana_commun shared database,
// `couleur` added to ref_evenements, ref_evenements_taches merged inline).
// Confirmed mechanically (replaying the full pre-squash set fails with
// "table ref_evenements already exists" at the 000003 file) and confirmed
// against application code: Evenement::$fillable includes `couleur`, which
// only exists in the newer version — so the newer version is what the app
// actually expects, even though the older one is what a fresh migrate
// would apply first today. This file uses the newer definition throughout,
// with plan_absences' later Google Calendar tracking columns folded in.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── ref_evenements ────────────────────────────────────────────────
        Schema::create('ref_evenements', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nom', 150);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->text('description')->nullable();
            $table->string('calendar_name', 200)->nullable()
                ->comment('Nom du calendrier Google Calendar cible — null = pas de synchro');
            $table->string('couleur', 2)->nullable()
                ->comment('colorId Google Calendar (\'1\' à \'11\') — null = couleur par défaut du calendrier cible');

            $table->index(['date_debut', 'date_fin'], 'idx_evenements_dates');
        });

        // ── ref_evenements_taches ─────────────────────────────────────────
        // Table pivot entre événements et tâches bloquées. Si un événement a
        // des lignes ici, les tâches concernées ne seront pas assignées lors
        // de la génération du planning pour les créneaux couverts par cet
        // événement. Aucune ligne → événement purement informatif.
        Schema::create('ref_evenements_taches', function (Blueprint $table) {
            $table->unsignedInteger('id_evenement');
            $table->unsignedTinyInteger('id_tache');

            $table->primary(['id_evenement', 'id_tache']);

            $table->foreign('id_evenement')
                ->references('id')->on('ref_evenements')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->foreign('id_tache')
                ->references('id')->on('ref_taches')
                ->onDelete('restrict')->onUpdate('cascade');
        });

        // ── ref_evenements_calendriers ────────────────────────────────────
        // Permet d'associer PLUSIEURS calendriers Google Calendar à un même
        // événement. google_calendar_id / google_event_id : suivi direct de
        // l'événement Google Calendar, réutilisé tel quel pour
        // events.patch()/events.delete() — pas de recherche par nom/date.
        Schema::create('ref_evenements_calendriers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_evenement');
            $table->string('calendar_name', 200);
            $table->string('google_calendar_id', 200)->nullable();
            $table->string('google_event_id', 200)->nullable();

            $table->unique(['id_evenement', 'calendar_name'], 'uq_evenements_calendriers');

            $table->foreign('id_evenement')
                ->references('id')->on('ref_evenements')
                ->onDelete('cascade')->onUpdate('cascade');
        });

        // ── plan_creneaux ─────────────────────────────────────────────────
        Schema::create('plan_creneaux', function (Blueprint $table) {
            $table->increments('id');
            $table->date('date')->unique('uq_planning_date');
        });

        // ── plan_absences ─────────────────────────────────────────────────
        // FK vers ref_personnes retirée : table hors DB (amana_commun) —
        // relation Eloquent uniquement.
        Schema::create('plan_absences', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_personne');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->string('raison', 255)->nullable();
            $table->string('google_calendar_id', 200)->nullable();
            $table->string('google_event_id', 200)->nullable();

            $table->index('id_personne', 'idx_absences_personne');
            $table->index(['date_debut', 'date_fin'], 'idx_absences_dates');
        });

        // ── plan_creneaux_evenements ──────────────────────────────────────
        Schema::create('plan_creneaux_evenements', function (Blueprint $table) {
            $table->unsignedInteger('id_planning');
            $table->unsignedInteger('id_evenement');

            $table->primary(['id_planning', 'id_evenement']);

            $table->foreign('id_planning')
                ->references('id')->on('plan_creneaux')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_evenement')
                ->references('id')->on('ref_evenements')
                ->onDelete('cascade')->onUpdate('cascade');
        });

        // ── plan_creneaux_taches ──────────────────────────────────────────
        // FK vers ref_personnes retirée : table hors DB (amana_commun) —
        // relation Eloquent uniquement.
        Schema::create('plan_creneaux_taches', function (Blueprint $table) {
            $table->unsignedInteger('id_planning');
            $table->unsignedTinyInteger('id_tache');
            $table->unsignedInteger('id_personne')->nullable()
                ->comment('NULL = tâche non assignée');

            $table->primary(['id_planning', 'id_tache']);

            $table->foreign('id_planning')
                ->references('id')->on('plan_creneaux')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_tache')
                ->references('id')->on('ref_taches')
                ->onDelete('restrict')->onUpdate('cascade');
        });

        // ── plan_restrictions ─────────────────────────────────────────────
        // FK vers ref_personnes retirée : table hors DB (amana_commun) —
        // relation Eloquent uniquement.
        Schema::create('plan_restrictions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_personne');
            $table->unsignedTinyInteger('id_tache');
            $table->enum('jour', ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche']);
            $table->boolean('autorise')->default(true);

            $table->unique(['id_personne', 'id_tache', 'jour'], 'uq_restrictions');

            $table->foreign('id_tache')
                ->references('id')->on('ref_taches')
                ->onDelete('restrict')->onUpdate('cascade');
        });

        // ── plan_echanges ─────────────────────────────────────────────────
        // Un échange est une demande d'échange de créneau entre deux
        // membres. Cycle de vie : en_attente → accepte/refuse/expire/annule.
        // FK vers ref_personnes retirée : table hors DB (amana_commun) —
        // relation Eloquent uniquement.
        Schema::create('plan_echanges', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('id_personne_demandeur')
                ->comment('Personne qui demande l\'échange');
            $table->unsignedInteger('id_creneau_demandeur')
                ->comment('id_planning du créneau du demandeur');
            $table->unsignedTinyInteger('id_tache_demandeur')
                ->comment('id_tache du slot du demandeur');

            $table->unsignedInteger('id_personne_cible')
                ->comment('Personne avec qui échanger');
            $table->unsignedInteger('id_creneau_cible')
                ->comment('id_planning du créneau cible');
            $table->unsignedTinyInteger('id_tache_cible')
                ->comment('id_tache du slot cible');

            $table->enum('statut', ['en_attente', 'accepte', 'refuse', 'expire', 'annule'])
                ->default('en_attente');
            $table->string('token_accept', 64)->unique()
                ->comment('Token URL pour accepter — invalidé après action');
            $table->string('token_refuse', 64)->unique()
                ->comment('Token URL pour refuser — invalidé après action');
            $table->timestamp('expires_at')
                ->comment('Date/heure d\'expiration = date du créneau du demandeur');

            $table->unsignedInteger('approuve_par')->nullable()
                ->comment('ID admin/gestionnaire qui a approuvé (si override)');
            $table->timestamps();

            $table->index('id_personne_demandeur');
            $table->index('id_personne_cible');
            $table->index('statut');
            $table->index('expires_at');

            $table->foreign('id_creneau_demandeur')
                ->references('id')->on('plan_creneaux')
                ->onDelete('cascade');
            $table->foreign('id_creneau_cible')
                ->references('id')->on('plan_creneaux')
                ->onDelete('cascade');
            $table->foreign('id_tache_demandeur')
                ->references('id')->on('ref_taches')
                ->onDelete('restrict');
            $table->foreign('id_tache_cible')
                ->references('id')->on('ref_taches')
                ->onDelete('restrict');
        });

        // ── plan_bilans_quotidiens ────────────────────────────────────────
        // Un seul bilan par date, deux groupes indépendants (Amana food /
        // Présences) avec leurs propres méta pour permettre des éditions
        // concurrentes sans écrasement. FK vers ref_personnes retirée :
        // table hors DB (amana_commun) — relation Eloquent uniquement.
        Schema::create('plan_bilans_quotidiens', function (Blueprint $table) {
            $table->increments('id');

            $table->date('date')->unique('uq_bilans_date');

            $table->decimal('montant_carte', 8, 2)->nullable()->default(null)
                ->comment('Montant collecté par carte bancaire — NULL = pas de cours ce jour-là');
            $table->decimal('montant_espece', 8, 2)->nullable()->default(null)
                ->comment('Montant collecté en espèces — NULL = pas de cours ce jour-là');
            $table->unsignedInteger('id_personne_maj_food')->nullable()
                ->comment('Dernière personne ayant modifié le groupe Amana food');
            $table->timestamp('maj_food_at')->nullable()
                ->comment('Date de dernière modification du groupe Amana food');

            $table->unsignedSmallInteger('nb_presents')->nullable()->default(null)
                ->comment('Nombre de personnes présentes sur place — NULL = pas de cours ce jour-là');
            $table->unsignedSmallInteger('nb_en_ligne')->nullable()->default(null)
                ->comment('Nombre de personnes connectées en ligne — NULL = pas de cours ce jour-là');
            $table->unsignedInteger('id_personne_maj_presence')->nullable()
                ->comment('Dernière personne ayant modifié le groupe Présences');
            $table->timestamp('maj_presence_at')->nullable()
                ->comment('Date de dernière modification du groupe Présences');

            $table->timestamps();
        });

        // ── plan_calendrier_evenements ────────────────────────────────────
        // Suivi de l'event_id Google Calendar créé pour chaque
        // (créneau, tâche, calendrier) — voir docblock détaillé dans
        // l'ancienne migration pour pourquoi ce n'est pas juste des colonnes
        // sur plan_creneaux_taches.
        Schema::create('plan_calendrier_evenements', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_planning');
            $table->unsignedTinyInteger('id_tache');
            $table->string('google_calendar_id', 200);
            $table->string('google_event_id', 200);
            $table->timestamps();

            $table->unique(
                ['id_planning', 'id_tache', 'google_calendar_id'],
                'uq_plan_calendrier_evenements'
            );

            $table->foreign('id_planning')
                ->references('id')->on('plan_creneaux')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_tache')
                ->references('id')->on('ref_taches')
                ->onDelete('cascade')->onUpdate('cascade');
        });

        // ── plan_rappels_envoyes ──────────────────────────────────────────
        // Suivi des rappels déjà envoyés pour éviter les doublons — voir
        // RappelService::envoyerRappelsImminents(). FK vers ref_personnes
        // retirée : table hors DB (amana_commun) — relation Eloquent
        // uniquement.
        Schema::create('plan_rappels_envoyes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_planning');
            $table->unsignedTinyInteger('id_tache');
            $table->unsignedInteger('id_personne');
            $table->string('type_rappel', 20);
            $table->timestamp('envoye_at');

            $table->unique(
                ['id_planning', 'id_tache', 'id_personne', 'type_rappel'],
                'uq_plan_rappels_envoyes'
            );

            $table->foreign('id_planning')
                ->references('id')->on('plan_creneaux')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_tache')
                ->references('id')->on('ref_taches')
                ->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_rappels_envoyes');
        Schema::dropIfExists('plan_calendrier_evenements');
        Schema::dropIfExists('plan_bilans_quotidiens');
        Schema::dropIfExists('plan_echanges');
        Schema::dropIfExists('plan_restrictions');
        Schema::dropIfExists('plan_creneaux_taches');
        Schema::dropIfExists('plan_creneaux_evenements');
        Schema::dropIfExists('plan_absences');
        Schema::dropIfExists('plan_creneaux');
        Schema::dropIfExists('ref_evenements_calendriers');
        Schema::dropIfExists('ref_evenements_taches');
        Schema::dropIfExists('ref_evenements');
    }
};
