<?php
// tests/Support/SchedulerEspion.php
//
// SchedulerMain dont regenerateFromImpactedDate() est remplacée : enregistre
// les dates reçues et renvoie un résultat imposé (ou lève une exception).
// La génération elle-même est testée dans SchedulerMainTest.

declare(strict_types=1);

namespace Tests\Support;

use App\Services\DataLoader;
use App\Services\RotationEngine;
use App\Services\SchedulerMain;
use Carbon\Carbon;
use Throwable;

final class SchedulerEspion extends SchedulerMain
{
    /** @var list<string> dates (Y-m-d) reçues, dans l'ordre */
    public array $dates = [];

    public ?Throwable $exception = null;

    public function __construct(DataLoader $loader, RotationEngine $engine, public ?array $retour = null)
    {
        parent::__construct($loader, $engine);
        $this->retour ??= self::retourParDefaut();
    }

    public static function retourParDefaut(): array
    {
        return [
            'resultat' => ['jours_generes' => 4, 'non_assignes' => 1, 'duree_ms' => 3.0],
            'dateDebutRegen' => '2026-10-02',
            'semaines' => 2,
            'regenererDepuis' => Carbon::parse('2026-10-02'),
            'aPartirDe' => null,
        ];
    }

    public function regenerateFromImpactedDate(Carbon $premiereDateImpactee): array
    {
        $this->dates[] = $premiereDateImpactee->toDateString();

        if ($this->exception) {
            throw $this->exception;
        }

        return $this->retour;
    }
}
