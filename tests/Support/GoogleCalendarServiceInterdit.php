<?php
// tests/Support/GoogleCalendarServiceInterdit.php
//
// GoogleCalendarService passe par le SDK Google (Google\Client, Guzzle) et NON par la façade
// Http de Laravel : Http::preventStrayRequests() ne le protège donc pas. Ce double, lié dans
// Tests\TestCase::setUp(), rend tout appel à Google impossible : « non configuré » pour les
// vérifications d'état, exception pour tout ce qui sortirait vers le réseau.
// Un test qui veut un autre comportement re-lie sa propre classe : $this->app->instance(...).

declare(strict_types=1);

namespace Tests\Support;

use App\Services\GoogleCalendarService;
use LogicException;

final class GoogleCalendarServiceInterdit extends GoogleCalendarService
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function getServiceAccountEmail(): ?string
    {
        return null;
    }

    public function getCalendar(string $calendarId): array
    {
        throw $this->interdit(__FUNCTION__);
    }

    public function createEvent(string $calendarId, array $event): string
    {
        throw $this->interdit(__FUNCTION__);
    }

    public function updateEvent(string $calendarId, string $eventId, array $event): void
    {
        throw $this->interdit(__FUNCTION__);
    }

    public function deleteEvent(string $calendarId, string $eventId): void
    {
        throw $this->interdit(__FUNCTION__);
    }

    public function partagerAvecUtilisateur(string $calendarId, string $email, string $role): void
    {
        throw $this->interdit(__FUNCTION__);
    }

    private function interdit(string $methode): LogicException
    {
        return new LogicException("Appel à Google Calendar ({$methode}) interdit en test : re-lier GoogleCalendarService avec un double.");
    }
}
