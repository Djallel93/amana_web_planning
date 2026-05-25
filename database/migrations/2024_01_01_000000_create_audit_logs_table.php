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
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 100)->comment('Type d\'action : create, update, delete, generate, login, logout');
            $table->string('module', 100)->comment('Module concerné : personnes, planning, restrictions, absences, evenements');
            $table->unsignedBigInteger('entity_id')->nullable()->comment('ID de l\'entité concernée');
            $table->string('entity_type', 100)->nullable()->comment('Classe du modèle : App\\Models\\Personne, etc.');
            $table->json('before')->nullable()->comment('État avant modification (null pour create)');
            $table->json('after')->nullable()->comment('État après modification (null pour delete)');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
