<?php
// tests/Support/LogEnregistre.php
//
// Remplaçant de Log pour Log::swap() : enregistre les appels sans Laravel
// démarré ni Mockery. Fonctionne avec la façade (résolution par swap).

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;

final class LogEnregistre
{
    /** @var list<array{niveau: string, message: string, contexte: array}> */
    public array $appels = [];

    public static function installer(): self
    {
        $faux = new self();
        Log::swap($faux);

        return $faux;
    }

    public static function retirer(): void
    {
        Facade::clearResolvedInstances();
    }

    public function __call(string $niveau, array $arguments): void
    {
        $this->appels[] = ['niveau' => $niveau, 'message' => (string) ($arguments[0] ?? ''), 'contexte' => $arguments[1] ?? []];
    }

    /** @return list<array> */
    public function appelsDeNiveau(string $niveau): array
    {
        return array_values(array_filter($this->appels, fn($a) => $a['niveau'] === $niveau));
    }
}
