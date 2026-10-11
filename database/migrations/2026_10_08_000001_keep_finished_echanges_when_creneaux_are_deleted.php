<?php
// database/migrations/2026_10_08_000001_keep_finished_echanges_when_creneaux_are_deleted.php
//
// Régénérer le planning supprime les créneaux (SchedulerMain::cleanExistingCreneaux()).
// plan_echanges avait ON DELETE CASCADE vers plan_creneaux : tout échange lié — en
// attente ET historique (accepté, refusé, expiré, annulé) — disparaissait donc en
// silence à chaque régénération.
//
// Désormais :
//   - les échanges EN ATTENTE sont annulés explicitement et notifiés par
//     SchedulerMain avant la suppression des créneaux ;
//   - les échanges terminés sont CONSERVÉS : les clés étrangères id_creneau_* passent
//     à ON DELETE SET NULL (colonnes nullable) ;
//   - date_creneau_demandeur / date_creneau_cible mémorisent la date des créneaux
//     (renseignées à la création, rattrapées ici pour les lignes existantes) afin que
//     l'historique reste lisible une fois la FK passée à NULL.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Dates mémorisées.
        Schema::table('plan_echanges', function (Blueprint $table): void {
            $table->date('date_creneau_demandeur')->nullable()->after('id_creneau_demandeur')
                ->comment('Date du créneau du demandeur — conservée si le créneau est supprimé');
            $table->date('date_creneau_cible')->nullable()->after('id_creneau_cible')
                ->comment('Date du créneau cible — conservée si le créneau est supprimé');
        });

        // 2. Rattrapage des lignes existantes.
        DB::statement('UPDATE plan_echanges e JOIN plan_creneaux c ON c.id = e.id_creneau_demandeur SET e.date_creneau_demandeur = c.date');
        DB::statement('UPDATE plan_echanges e JOIN plan_creneaux c ON c.id = e.id_creneau_cible SET e.date_creneau_cible = c.date');

        // 3. CASCADE → SET NULL (une phase par appel : la FK doit être supprimée
        //    avant de rendre la colonne nullable, puis recréée).
        Schema::table('plan_echanges', function (Blueprint $table): void {
            $table->dropForeign(['id_creneau_demandeur']);
            $table->dropForeign(['id_creneau_cible']);
        });

        Schema::table('plan_echanges', function (Blueprint $table): void {
            $table->unsignedInteger('id_creneau_demandeur')->nullable()
                ->comment('id_planning du créneau du demandeur — NULL si le créneau a été supprimé')->change();
            $table->unsignedInteger('id_creneau_cible')->nullable()
                ->comment('id_planning du créneau cible — NULL si le créneau a été supprimé')->change();
        });

        Schema::table('plan_echanges', function (Blueprint $table): void {
            $table->foreign('id_creneau_demandeur')->references('id')->on('plan_creneaux')->nullOnDelete();
            $table->foreign('id_creneau_cible')->references('id')->on('plan_creneaux')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('plan_echanges', function (Blueprint $table): void {
            $table->dropForeign(['id_creneau_demandeur']);
            $table->dropForeign(['id_creneau_cible']);
        });

        // Les lignes dont un créneau a été supprimé ne peuvent plus respecter NOT NULL :
        // elles sont supprimées (c'était, avant cette migration, leur sort à la suppression).
        DB::table('plan_echanges')->whereNull('id_creneau_demandeur')->orWhereNull('id_creneau_cible')->delete();

        Schema::table('plan_echanges', function (Blueprint $table): void {
            $table->unsignedInteger('id_creneau_demandeur')->nullable(false)
                ->comment('id_planning du créneau du demandeur')->change();
            $table->unsignedInteger('id_creneau_cible')->nullable(false)
                ->comment('id_planning du créneau cible')->change();
        });

        Schema::table('plan_echanges', function (Blueprint $table): void {
            $table->foreign('id_creneau_demandeur')->references('id')->on('plan_creneaux')->onDelete('cascade');
            $table->foreign('id_creneau_cible')->references('id')->on('plan_creneaux')->onDelete('cascade');
            $table->dropColumn(['date_creneau_demandeur', 'date_creneau_cible']);
        });
    }
};
