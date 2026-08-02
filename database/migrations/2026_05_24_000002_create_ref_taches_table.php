<?php
// database/migrations/2026_05_24_000002_create_ref_taches_table.php
//
// Anciennement fusionnée avec ref_roles/ref_personnes/ref_personnes_roles
// dans create_base_tables.php — ces trois dernières vivent maintenant dans
// amana_shared (amana_commun), ref_taches reste ici : c'est un référentiel
// de tâches propre au planning, pas une notion partagée entre apps AMANA.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_taches');
    }
};
