<?php
// database/migrations/2026_05_24_000002_create_ref_tables.php
//
// Consolidated from (squash, see git history for the originals):
//   - 2026_05_24_000002_create_ref_taches_table.php
//   - 2026_05_28_000006_create_ref_settings_table.php
//   - 2026_06_12_000001_add_inscription_ouverte_setting.php (data only)
//   - 2026_07_03_000002_add_annulation_cours_settings.php (data only)
//   - 2026_07_17_000001_create_ref_calendriers_google_table.php
//
// All ref_ reference/lookup tables that aren't part of the base auth
// cluster. Depends on ref_applications (create_base_tables.php, runs
// first).
//
// ref_taches duplicate-definition bug (the one flagged going into this
// squash): this table was ALSO defined inline inside the old
// create_base_tables.php, with a thinner column set (no
// description_calendrier, older code-comment). That inline definition was
// a leftover from before ref_taches was split out into its own file — this
// standalone, richer definition is the one the app's Tache model actually
// expects ($fillable includes description_calendrier) and is authoritative
// here. Confirmed mechanically: replaying the full pre-squash migration set
// in execution order hits "table ref_taches already exists" at exactly this
// file, because the old create_base_tables.php's thinner ref_taches wins
// the race and this richer one collides with it.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── ref_taches ────────────────────────────────────────────────────
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

        // ── ref_settings ──────────────────────────────────────────────────
        // Stocke les paramètres de configuration par application AMANA.
        // Toujours filtrer par id_application lors de la lecture.
        Schema::create('ref_settings', function (Blueprint $table) {
            $table->tinyIncrements('id');

            $table->unsignedTinyInteger('id_application')->nullable()
                ->comment('NULL = paramètre global, sinon lié à une application');

            $table->string('cle', 100)
                ->comment('Identifiant technique du paramètre (ex: heure_cours)');

            $table->string('valeur', 500)
                ->comment('Valeur stockée sous forme de chaîne, castée à la lecture');

            $table->enum('type', ['string', 'integer', 'time', 'boolean'])
                ->default('string')
                ->comment('Type de casting appliqué à la valeur lors de la lecture');

            $table->string('libelle', 200)
                ->comment('Label lisible affiché dans l\'UI (ex: Heure du cours)');

            $table->text('description')->nullable()
                ->comment('Description longue optionnelle pour l\'aide contextuelle');

            $table->unique(['id_application', 'cle'], 'uq_settings_app_cle');

            $table->foreign('id_application')
                ->references('id')->on('ref_applications')
                ->onDelete('cascade')->onUpdate('cascade');
        });

        // ── ref_calendriers_google ────────────────────────────────────────
        // Registre géré manuellement des calendriers Google Calendar connus
        // (un compte de service ne peut pas lister ses calendriers partagés
        // via l'API — voir commentaire détaillé dans l'ancienne migration).
        Schema::create('ref_calendriers_google', function (Blueprint $table) {
            $table->increments('id');
            $table->string('calendar_id', 200)->unique();
            $table->string('nom', 200);
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->boolean('inclure_nouveaux_membres')->default(false)
                ->comment('Si true, ce calendrier est partagé automatiquement avec chaque nouveau bénévole validé (voir CalendarSharingService)');
            $table->timestamp('derniere_verification_at')->nullable();
            $table->timestamps();
        });

        // ── Données (préservées depuis les anciennes migrations) ───────────
        $planningId = DB::table('ref_applications')->where('code', 'planning')->value('id');

        DB::table('ref_settings')->updateOrInsert(
            ['id_application' => $planningId, 'cle' => 'inscription_ouverte'],
            [
                'valeur' => '1',
                'type' => 'boolean',
                'libelle' => 'Inscriptions ouvertes',
                'description' => "Active ou désactive le formulaire public d'inscription (/inscription). Seuls les administrateurs peuvent modifier ce paramètre.",
            ]
        );

        DB::table('ref_taches')->updateOrInsert(
            ['code' => 'annulation_cours'],
            ['libelle' => 'Annulation Cours', 'actif' => false, 'description' => '']
        );

        foreach ([
            [
                'cle' => 'offset_annulation_cours_debut',
                'valeur' => '-360',
                'type' => 'integer',
                'libelle' => 'Annulation cours : début (min)',
                'description' => null,
            ],
            [
                'cle' => 'offset_annulation_cours_fin',
                'valeur' => '-345',
                'type' => 'integer',
                'libelle' => 'Annulation cours : fin (min)',
                'description' => null,
            ],
            [
                'cle' => 'calendar_annulation_cours',
                'valeur' => 'AMANA - Communications',
                'type' => 'string',
                'libelle' => 'Annulation Cours',
                'description' => null,
            ],
        ] as $s) {
            DB::table('ref_settings')->updateOrInsert(
                ['id_application' => $planningId, 'cle' => $s['cle']],
                [
                    'valeur' => $s['valeur'],
                    'type' => $s['type'],
                    'libelle' => $s['libelle'],
                    'description' => $s['description'],
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_calendriers_google');
        Schema::dropIfExists('ref_settings');
        Schema::dropIfExists('ref_taches');
    }
};
