<?php
// tests/TestCase.php
//
// Base des tests qui démarrent l'application Laravel (Feature, et Unit qui
// ont besoin du conteneur). Les tests de logique pure (voir DateHelperTest)
// étendent directement PHPUnit\Framework\TestCase et n'ont pas besoin de
// ceci.
//
// Pas de base de données ici : les tests qui en ont besoin ajoutent le trait
// Tests\Concerns\RefreshesBothDatabases.

declare(strict_types=1);

namespace Tests;

use Amana\Shared\Helpers\AuditHelper as AuditHelperPartage;
use Amana\Shared\Models\Setting;
use App\Helpers\AuditHelper;
use App\Models\Personne;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use ReflectionProperty;
use Tests\Support\GoogleCalendarServiceInterdit;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Le manifeste Vite n'existe pas en CI (pas de `npm run build`) : sans
        // ceci, le rendu du gabarit racine Inertia lève une exception.
        $this->withoutVite();

        // Deux garde-fous « jamais d'appel sortant » :
        //  - la file de test est `sync` : sans Bus::fake(), SynchroniserGoogleCalendar
        //    s'exécuterait sur-le-champ ;
        //  - Http::preventStrayRequests() couvre le client HTTP de Laravel (webhook Make.com),
        //    mais PAS le SDK Google, qu'on neutralise en liant un double.
        Http::preventStrayRequests();
        $this->app->instance(GoogleCalendarService::class, new GoogleCalendarServiceInterdit());

        $this->reinitialiserCachesStatiques();
    }

    /**
     * Quatre caches STATIQUES survivent d'un test à l'autre (le process PHP est
     * partagé) alors que chaque test annule sa transaction et que les ids
     * auto-incrémentés ne reviennent pas en arrière :
     *  - Setting::$cache               (amana_shared) — paramètres lus dans ref_settings ;
     *  - Amana\Shared\Helpers\AuditHelper — id d'application ; c'est CELUI de la fonction globale
     *    audit() (contrôleurs, services), il a un clearCache() ;
     *  - App\Helpers\AuditHelper::$applicationId — id de l'application 'planning', pas de clearCache() ;
     *  - Personne::$tacheCodesById     — Tache::pluck('code', 'id') de peutFaireTache().
     * Sans remise à zéro, un test lirait l'id/valeur du test précédent.
     */
    protected function reinitialiserCachesStatiques(): void
    {
        Setting::clearCache();
        AuditHelperPartage::clearCache(); // celui que la fonction globale audit() utilise réellement

        foreach ([[AuditHelper::class, 'applicationId'], [Personne::class, 'tacheCodesById']] as [$classe, $propriete]) {
            (new ReflectionProperty($classe, $propriete))->setValue(null);
        }
    }

    /**
     * E-mails envoyés via le transport « array » (aucun envoi réel).
     * Même principe que amana_shared/tests/TestCase::mails(), recopié ici
     * volontairement plutôt qu'importé (classe de test, non exportée).
     *
     * @return Collection<int, array{to: string, subject: string, html: string}>
     */
    protected function mails(): Collection
    {
        $transport = $this->app['mail.manager']->mailer('array')->getSymfonyTransport();

        return $transport->messages()->map(function ($sent) {
            $email = $sent->getOriginalMessage();

            return [
                'to' => $email->getTo()[0]->getAddress(),
                'subject' => (string) $email->getSubject(),
                'html' => (string) $email->getHtmlBody(),
            ];
        })->values();
    }

    protected function viderMails(): void
    {
        $this->app['mail.manager']->mailer('array')->getSymfonyTransport()->flush();
    }
}
