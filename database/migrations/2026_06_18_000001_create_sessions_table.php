<?php
// database/migrations/2026_06_18_000001_create_sessions_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée la table sessions pour le driver SESSION_DRIVER=database.
 *
 * Utilisée en production (IONOS) pour stocker les sessions côté serveur.
 * En local, SESSION_DRIVER=file suffit et cette table reste vide.
 *
 * Structure identique au stub Laravel 11/13 officiel.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};