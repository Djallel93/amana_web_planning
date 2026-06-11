<?php
// database/migrations/2024_01_01_000002b_create_evenements_taches_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table pivot entre événements et tâches bloquées.
 *
 * Si un événement a des lignes dans cette table, les tâches concernées
 * ne seront pas assignées lors de la génération du planning pour les
 * créneaux couverts par cet événement.
 *
 * Si un événement n'a aucune ligne ici → événement purement informatif,
 * n'affecte pas la génération.
 */
return new class extends Migration {
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_evenements_taches');
    }
};