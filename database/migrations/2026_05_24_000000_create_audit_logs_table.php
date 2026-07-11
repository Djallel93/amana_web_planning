<?php
// database/migrations/2024_01_01_000000_create_audit_logs_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration : table des journaux d'audit
 * Toute action sensible (create, update, delete, generate) est loguée ici.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Pas de contrainte FK intentionnellement — même approche que la table sessions.
            // L'historique d'audit est conservé même si la personne est supprimée.
            // NULL = action système sans utilisateur identifié (webhook job, tentative
            // de connexion échouée avant Auth::user() soit résolu).
            $table->unsignedInteger('user_id')->nullable()
                ->comment('ID de ref_personnes — null pour les actions système');

            // Contrainte FK ajoutée dans 2026_05_28_000003 (une fois ref_applications
            // créée — cette table doit rester exécutable avant elle). Permet à
            // plusieurs applications AMANA de partager cette même table d'audit.
            $table->unsignedTinyInteger('id_application')->nullable()
                ->comment('ID de ref_applications — application à l\'origine de l\'entrée');

            $table->string('action', 100)->comment('Type d\'action : create, update, delete, generate, login, logout, webhook');
            $table->string('module', 100)->comment('Module concerné : personnes, planning, restrictions, absences, evenements, auth, settings');
            $table->unsignedBigInteger('entity_id')->nullable()->comment('ID de l\'entité concernée');
            $table->string('entity_type', 100)->nullable()->comment('Classe du modèle : App\\Models\\Personne, etc.');
            $table->json('before')->nullable()->comment('État avant modification (null pour create)');
            $table->json('after')->nullable()->comment('État après modification (null pour delete)');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('id_application');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};