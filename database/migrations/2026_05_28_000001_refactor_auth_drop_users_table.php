<?php
// database/migrations/2026_05_28_000001_refactor_auth_drop_users_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Étape 1 du refactoring auth.
 *
 * - Supprime la table users (remplacée par ref_personnes)
 * - Supprime password_reset_tokens (sera recrée via ref_personnes)
 * - Recrée sessions sans la FK vers users (pointera vers ref_personnes plus tard)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Supprimer sessions en premier (FK vers users)
        Schema::dropIfExists('sessions');

        // Supprimer password_reset_tokens
        Schema::dropIfExists('password_reset_tokens');

        // Supprimer users
        Schema::dropIfExists('users');

        // Recréer sessions sans FK (la FK sera gérée par l'application, pas la BDD)
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');

        // Restaurer users
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // Restaurer password_reset_tokens
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Restaurer sessions avec FK vers users
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }
};
