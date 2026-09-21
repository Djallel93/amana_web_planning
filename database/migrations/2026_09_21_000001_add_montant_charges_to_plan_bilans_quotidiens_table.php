<?php
// database/migrations/2026_09_21_000001_add_montant_charges_to_plan_bilans_quotidiens_table.php
//
// Ajoute le suivi des charges Amana food (ce que la nourriture a coûté ce
// jour-là) à plan_bilans_quotidiens, en plus de montant_carte/montant_espece.
//
// Même convention NULL vs 0 que les colonnes existantes (voir Bilan et
// BilanController) : NULL = pas de cours ce jour-là, 0 = un cours a eu
// lieu et la nourriture a été offerte (aucune charge réelle).

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('plan_bilans_quotidiens', function (Blueprint $table): void {
            $table->decimal('montant_charges', 8, 2)->nullable()->default(null)
                ->after('montant_espece')
                ->comment('Coût de la nourriture Amana food — NULL = pas de cours ce jour-là, 0 = nourriture offerte');
        });
    }

    public function down(): void
    {
        Schema::table('plan_bilans_quotidiens', function (Blueprint $table): void {
            $table->dropColumn('montant_charges');
        });
    }
};
