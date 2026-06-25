<?php
// app/Http/Controllers/DiagnosticController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Contrôleur de diagnostic SMTP — réservé aux administrateurs.
 *
 * Accessible via GET /diagnostic-mail (middleware role:admin).
 * Permet de tester la connexion SMTP et d'envoyer un email de test
 * sans quitter le navigateur. Affiche la configuration active (obfusquée)
 * et le résultat détaillé de la tentative d'envoi.
 *
 * À UTILISER UNIQUEMENT pour diagnostiquer les problèmes d'email en production.
 */
class DiagnosticController extends Controller
{
    /**
     * Affiche la page de diagnostic avec la config SMTP actuelle.
     */
    public function index(): View
    {
        $config = $this->getSmtpConfig();

        return view('diagnostic.mail', [
            'config' => $config,
            'resultat' => null,
            'testEmail' => null,
        ]);
    }

    /**
     * Envoie un email de test et retourne le résultat détaillé.
     */
    public function tester(Request $request): View
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ], [
            'email.required' => 'L\'adresse email de test est obligatoire.',
            'email.email' => 'Format d\'email invalide.',
        ]);

        $destinataire = $request->input('email');
        $config = $this->getSmtpConfig();
        $resultat = $this->envoyerEmailTest($destinataire);

        return view('diagnostic.mail', [
            'config' => $config,
            'resultat' => $resultat,
            'testEmail' => $destinataire,
        ]);
    }

    // ── Helpers privés ─────────────────────────────────────────────────────

    /**
     * Retourne la configuration SMTP active, avec le mot de passe obfusqué.
     */
    private function getSmtpConfig(): array
    {
        $mailerConfig = config('mail.mailers.' . config('mail.default'), []);
        $password = $mailerConfig['password'] ?? '';

        return [
            'mailer' => config('mail.default'),
            'host' => $mailerConfig['host'] ?? '—',
            'port' => $mailerConfig['port'] ?? '—',
            'scheme' => $mailerConfig['scheme'] ?? '(non défini)',
            'username' => $mailerConfig['username'] ?? '—',
            'password' => $password ? str_repeat('*', min(8, strlen($password))) . '…' : '(vide)',
            'from_address' => config('mail.from.address', '—'),
            'from_name' => config('mail.from.name', '—'),
            'queue' => config('queue.default'),
            'log_channel' => config('logging.default'),
        ];
    }

    /**
     * Tente d'envoyer un email de test et retourne un tableau de résultat.
     */
    private function envoyerEmailTest(string $destinataire): array
    {
        $debut = microtime(true);

        Log::info('[DiagnosticMail] Début test SMTP', [
            'destinataire' => $destinataire,
            'mailer' => config('mail.default'),
            'host' => config('mail.mailers.' . config('mail.default') . '.host'),
            'port' => config('mail.mailers.' . config('mail.default') . '.port'),
            'scheme' => config('mail.mailers.' . config('mail.default') . '.scheme'),
            'username' => config('mail.mailers.' . config('mail.default') . '.username'),
        ]);

        try {
            Mail::raw(
                'Email de test envoyé depuis AMANA Planning — ' . now()->toDateTimeString() . "\n\n"
                . 'Si vous recevez ce message, la configuration SMTP fonctionne correctement.',
                function ($message) use ($destinataire) {
                    $message->to($destinataire)
                        ->subject('[AMANA] Test SMTP — ' . now()->format('d/m/Y H:i:s'));
                }
            );

            $duree = round((microtime(true) - $debut) * 1000);

            Log::info('[DiagnosticMail] Email de test envoyé avec succès', [
                'destinataire' => $destinataire,
                'duree_ms' => $duree,
            ]);

            return [
                'succes' => true,
                'message' => 'Email envoyé avec succès.',
                'duree_ms' => $duree,
                'destinataire' => $destinataire,
                'erreur' => null,
                'trace' => null,
            ];

        } catch (\Throwable $e) {
            $duree = round((microtime(true) - $debut) * 1000);

            Log::error('[DiagnosticMail] Échec envoi email de test', [
                'destinataire' => $destinataire,
                'duree_ms' => $duree,
                'erreur' => $e->getMessage(),
                'classe' => get_class($e),
                'fichier' => $e->getFile() . ':' . $e->getLine(),
            ]);

            return [
                'succes' => false,
                'message' => 'Échec de l\'envoi.',
                'duree_ms' => $duree,
                'destinataire' => $destinataire,
                'erreur' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];
        }
    }
}