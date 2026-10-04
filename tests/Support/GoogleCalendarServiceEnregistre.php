<?php
// tests/Support/GoogleCalendarServiceEnregistre.php
//
// Double de GoogleCalendarService « configuré » qui n'appelle jamais Google : enregistre les
// partages demandés, ou lève l'exception du SDK pour les e-mails listés dans $refuses.
// Usage : $this->app->instance(GoogleCalendarService::class, $double = new GoogleCalendarServiceEnregistre());

declare(strict_types=1);

namespace Tests\Support;

use App\Services\GoogleCalendarService;
use Google\Service\Exception as GoogleServiceException;

final class GoogleCalendarServiceEnregistre extends GoogleCalendarService
{
    /** @var list<array{calendrier: string, email: string, role: string}> */
    public array $partages = [];

    /**
     * @param list<string>          $calendriersRefuses ids de calendrier pour lesquels le partage échoue
     * @param array<string, int>    $erreursAcces       id de calendrier => code HTTP renvoyé par getCalendar()
     *                                                  (un id absent de la liste est accessible)
     */
    public function __construct(public array $calendriersRefuses = [], public array $erreursAcces = []) {}

    public function getCalendar(string $calendarId): array
    {
        if (isset($this->erreursAcces[$calendarId])) {
            throw new GoogleServiceException('erreur simulée', $this->erreursAcces[$calendarId]);
        }

        return ['id' => $calendarId, 'summary' => 'Calendrier de test'];
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function getServiceAccountEmail(): ?string
    {
        return 'compte-de-service@example.test';
    }

    public function partagerAvecUtilisateur(string $calendarId, string $email, string $role): void
    {
        if (in_array($calendarId, $this->calendriersRefuses, true)) {
            throw new GoogleServiceException('Forbidden', 403);
        }
        $this->partages[] = ['calendrier' => $calendarId, 'email' => $email, 'role' => $role];
    }
}
