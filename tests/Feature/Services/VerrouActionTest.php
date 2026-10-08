<?php
// tests/Feature/Services/VerrouActionTest.php
//
// Le verrou d'action (Cache::lock) qui empêche une double soumission ou deux régénérations du
// planning en parallèle. Store de cache « array » en test : un verrou pris dans le test est vu
// par le code testé, exactement comme deux requêtes se voient via le store `database` en prod.

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\ActionEnCoursException;
use App\Services\VerrouAction;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class VerrouActionTest extends TestCase
{
    private VerrouAction $verrou;

    protected function setUp(): void
    {
        parent::setUp();
        $this->verrou = new VerrouAction();
    }

    public function test_execute_l_action_et_renvoie_son_resultat(): void
    {
        $this->assertSame(42, $this->verrou->executer('cle', fn() => 42));
    }

    public function test_le_verrou_est_relache_apres_l_action(): void
    {
        $this->verrou->executer('cle', fn() => null);

        $this->assertSame('de nouveau', $this->verrou->executer('cle', fn() => 'de nouveau'));
    }

    public function test_le_verrou_est_relache_meme_quand_l_action_leve_une_exception(): void
    {
        try {
            $this->verrou->executer('cle', function () {
                throw new RuntimeException('boom');
            });
            $this->fail('L\'exception de l\'action doit remonter.');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame('libre', $this->verrou->executer('cle', fn() => 'libre'), 'le verrou n\'est pas resté bloqué');
    }

    public function test_refuse_tout_de_suite_une_action_deja_en_cours(): void
    {
        Cache::lock('verrou:cle', 60)->get();
        $executee = false;

        try {
            $this->verrou->executer('cle', function () use (&$executee) {
                $executee = true;
            });
            $this->fail('ActionEnCoursException attendue.');
        } catch (ActionEnCoursException $e) {
            $this->assertSame('cle', $e->cle);
            $this->assertSame(ActionEnCoursException::MESSAGE_PAR_DEFAUT, $e->getMessage());
        }

        $this->assertFalse($executee, 'l\'action ne doit pas s\'exécuter pendant que le verrou est pris');
    }

    public function test_le_message_personnalise_remplace_le_message_par_defaut(): void
    {
        Cache::lock('verrou:cle', 60)->get();

        $this->expectException(ActionEnCoursException::class);
        $this->expectExceptionMessage('Déjà en cours, patientez.');

        $this->verrou->executer('cle', fn() => null, 0, 'Déjà en cours, patientez.');
    }

    public function test_un_verrou_pris_sur_une_cle_ne_bloque_pas_une_autre_cle(): void
    {
        Cache::lock('verrou:absence:1', 60)->get();

        $this->assertSame('ok', $this->verrou->executer('absence:2', fn() => 'ok'));
    }

    public function test_avec_attente_abandonne_si_le_verrou_reste_pris(): void
    {
        Cache::lock('verrou:cle', 60)->get();

        $this->expectException(ActionEnCoursException::class);

        // Vraie attente d'une seconde (block() ne suit pas l'horloge simulée).
        $this->verrou->executer('cle', fn() => null, 1);
    }

    public function test_avec_attente_s_execute_directement_quand_le_verrou_est_libre(): void
    {
        $this->assertSame('ok', $this->verrou->executer('cle', fn() => 'ok', 5));
        $this->assertSame('ok', $this->verrou->executer('cle', fn() => 'ok', 5), 'et il est bien relâché ensuite');
    }
}
