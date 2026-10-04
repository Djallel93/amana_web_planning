<?php
// app/Services/InvitationLinkService.php

declare(strict_types=1);

namespace App\Services;

use App\Models\Personne;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;
use RuntimeException;

/**
 * Génère le lien « définir mon mot de passe » joint aux e-mails d'invitation
 * (candidature validée / invitation renvoyée) — appelé depuis
 * Admin\CandidaturesController::valider() et ::renvoyerInvitation().
 *
 * Pourquoi un service dédié : Password::broker() est typé par le CONTRAT
 * Illuminate\Contracts\Auth\PasswordBroker, qui ne déclare pas createToken()
 * (seulement présent sur l'implémentation concrète). Le rétrécissement de
 * type est donc fait ici, une seule fois, au lieu d'être dupliqué dans le
 * contrôleur — et PHPStan (niveau 3) reste satisfait sans baseline.
 *
 * Le jeton est celui du broker 'personnes' (table password_reset_tokens dans
 * amana_commun) : il est consommé par AuthController::resetPassword(), comme
 * pour un « mot de passe oublié ».
 */
class InvitationLinkService
{
    private const BROKER = 'personnes';

    /**
     * Crée un jeton de réinitialisation pour $personne et renvoie l'URL
     * absolue de la page « nouveau mot de passe » qui le porte.
     *
     * @throws RuntimeException si le broker configuré n'expose pas createToken()
     */
    public function urlDefinitionMotDePasse(Personne $personne): string
    {
        $broker = Password::broker(self::BROKER);

        if (!$broker instanceof PasswordBroker) {
            throw new RuntimeException(sprintf(
                "Le broker de mots de passe '%s' ne permet pas de créer un jeton (%s attendu, %s reçu).",
                self::BROKER,
                PasswordBroker::class,
                $broker::class,
            ));
        }

        $token = $broker->createToken($personne);

        return route('password.reset', ['token' => $token, 'email' => $personne->email]);
    }
}
