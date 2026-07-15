<?php
// database/migrations/2026_07_14_000002_make_bilan_valeurs_nullable.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rend nullable les 4 colonnes de valeurs de plan_bilans_quotidiens et
 * retire leur défaut à 0.
 *
 * Jusqu'ici, `montant_carte`/`montant_espece`/`nb_presents`/`nb_en_ligne`
 * étaient NOT NULL DEFAULT 0 — impossible de distinguer « 0 personne
 * présente / 0 € collecté » (une vraie valeur saisie) de « pas de cours
 * ce jour-là » (jour de semaine, vacances, cours annulé…). Les deux cas
 * se retrouvaient indiscernables à 0 en base, et un 0 s'affichait dans le
 * graphique des statistiques même les jours sans cours.
 *
 * À partir de cette migration :
 *   - NULL = pas de cours ce jour-là (valeur volontairement absente,
 *     via le bouton "Réinitialiser" ou simplement jamais saisie) ;
 *   - 0    = un cours a bien eu lieu, et la valeur réelle est zéro.
 *
 * Note : les lignes déjà existantes avant cette migration gardent leur 0
 * actuel tel quel — on ne peut pas deviner rétroactivement si ce 0 était
 * une vraie saisie ou une valeur jamais renseignée. Seules les nouvelles
 * lignes (et les réinitialisations manuelles) bénéficieront de la
 * distinction NULL / 0 à partir de maintenant.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('plan_bilans_quotidiens', function (Blueprint $table) {
            $table->decimal('montant_carte', 8, 2)->nullable()->default(null)
                ->comment('Montant collecté par carte bancaire — NULL = pas de cours ce jour-là')
                ->change();
            $table->decimal('montant_espece', 8, 2)->nullable()->default(null)
                ->comment('Montant collecté en espèces — NULL = pas de cours ce jour-là')
                ->change();
            $table->unsignedSmallInteger('nb_presents')->nullable()->default(null)
                ->comment('Nombre de personnes présentes sur place — NULL = pas de cours ce jour-là')
                ->change();
            $table->unsignedSmallInteger('nb_en_ligne')->nullable()->default(null)
                ->comment('Nombre de personnes connectées en ligne — NULL = pas de cours ce jour-là')
                ->change();
        });
    }

    public function down(): void
    {
        // Retour à l'état d'origine : NOT NULL DEFAULT 0. Toute ligne
        // contenant NULL à ce moment-là est ramenée à 0 pour respecter la
        // contrainte NOT NULL rétablie juste après.
        DB::table('plan_bilans_quotidiens')->whereNull('montant_carte')->update(['montant_carte' => 0]);
        DB::table('plan_bilans_quotidiens')->whereNull('montant_espece')->update(['montant_espece' => 0]);
        DB::table('plan_bilans_quotidiens')->whereNull('nb_presents')->update(['nb_presents' => 0]);
        DB::table('plan_bilans_quotidiens')->whereNull('nb_en_ligne')->update(['nb_en_ligne' => 0]);

        Schema::table('plan_bilans_quotidiens', function (Blueprint $table) {
            $table->decimal('montant_carte', 8, 2)->default(0)
                ->comment('Montant collecté par carte bancaire')
                ->change();
            $table->decimal('montant_espece', 8, 2)->default(0)
                ->comment('Montant collecté en espèces')
                ->change();
            $table->unsignedSmallInteger('nb_presents')->default(0)
                ->comment('Nombre de personnes présentes sur place')
                ->change();
            $table->unsignedSmallInteger('nb_en_ligne')->default(0)
                ->comment('Nombre de personnes connectées en ligne')
                ->change();
        });
    }
};
