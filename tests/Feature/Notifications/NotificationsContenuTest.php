<?php
// tests/Feature/Notifications/NotificationsContenuTest.php
//
// Le CONTENU réel des e-mails envoyés par l'application, rendu par le vrai transport « array »
// (MAIL_MAILER=array, aucun envoi) : destinataire, objet, informations clés, liens, logo en pièce
// jointe, et surtout aucune injection de HTML depuis des champs saisis par des utilisateurs.
// (Les envois eux-mêmes — qui est prévenu, quand — sont testés avec Notification::fake() dans les
// tests de service et de flux ; ici, volontairement pas de fake.)

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Echange;
use App\Models\Personne;
use App\Notifications\CandidatureValideeDejaInscritNotification;
use App\Notifications\CandidatureValideeNotification;
use App\Notifications\Echanges\EchangeAccepteNotification;
use App\Notifications\Echanges\EchangeAnnuleNotification;
use App\Notifications\Echanges\EchangeDemandeNotification;
use App\Notifications\Echanges\EchangeExpireNotification;
use App\Notifications\Echanges\EchangeRefuseNotification;
use App\Notifications\NouveauMembreNotification;
use App\Notifications\PlanningGenereNotification;
use App\Notifications\RappelCreneauNotification;
use Carbon\Carbon;
use Database\Factories\TacheFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreeDonneesPlanning;
use Tests\Concerns\RefreshesBothDatabases;
use Tests\TestCase;

class NotificationsContenuTest extends TestCase
{
    use CreeDonneesPlanning;
    use RefreshesBothDatabases;

    private Personne $alice;

    private Personne $bilal;

    private Echange $echange;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-09-30 09:00:00');
        $this->tachesDeRotation();
        $this->alice = Personne::factory()->create(['prenom' => 'Alice', 'nom' => 'Alpha', 'email' => 'alice@example.test']);
        $this->bilal = Personne::factory()->create(['prenom' => 'Bilal', 'nom' => 'Bravo', 'email' => 'bilal@example.test']);
        $this->assigner($this->alice, '2026-10-02', 'entree');
        $this->assigner($this->bilal, '2026-10-09', 'entree');
        $entree = TacheFactory::pourCode('entree')->id;
        $this->echange = Echange::factory()->create([
            'id_personne_demandeur' => $this->alice->id, 'id_creneau_demandeur' => $this->creneauLe('2026-10-02')->id, 'id_tache_demandeur' => $entree,
            'id_personne_cible' => $this->bilal->id, 'id_creneau_cible' => $this->creneauLe('2026-10-09')->id, 'id_tache_cible' => $entree,
        ]);
    }

    /** Envoie réellement (transport « array ») et renvoie l'unique message produit. @return array{to: string, subject: string, html: string} */
    private function envoyer(Personne $destinataire, $notification): array
    {
        $this->viderMails();
        $destinataire->notify($notification);

        $this->assertCount(1, $this->mails(), 'exactement un e-mail');

        return $this->mails()->first();
    }

    private function texte(array $mail): string
    {
        $sansStyle = preg_replace('#<(style|head)[^>]*>.*?</\1>#s', '', $mail['html']);

        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($sansStyle), ENT_QUOTES | ENT_HTML5)));
    }

    private function item(array $surcharge = []): array
    {
        return array_replace(['code' => 'entree', 'nom' => 'Entrée', 'heure_debut' => '20:00', 'heure_fin' => '21:00', 'description' => 'Accueillir les élèves', 'email' => 'alice@example.test'], $surcharge);
    }

    // ══ Rappels ═══════════════════════════════════════════════════════════

    /** @return array<string, array{string, string}> */
    public static function typesDeRappel(): array
    {
        return ['3 jours avant' => ['3_jours', 'Dans 3 jours'], 'jour J' => ['jour_j', "Aujourd'hui"], '3 heures avant' => ['3h_avant', 'Dans 3 heures']];
    }

    #[DataProvider('typesDeRappel')]
    public function test_le_rappel_annonce_le_delai_la_tache_et_les_horaires(string $type, string $badge): void
    {
        $mail = $this->envoyer($this->alice, new RappelCreneauNotification($this->item(), $type, '2026-10-02'));
        $texte = $this->texte($mail);

        $this->assertSame('alice@example.test', $mail['to']);
        $this->assertSame('Rappel — Entrée (vendredi 2 octobre)', $mail['subject']);
        $this->assertStringContainsString($badge, $texte);
        $this->assertStringContainsString('Cher Alice', $texte);
        $this->assertStringContainsString('Entrée', $texte);
        $this->assertStringContainsString('Vendredi 2 octobre', $texte);
        $this->assertStringContainsString('20:00 – 21:00', $texte);
        $this->assertStringContainsString('Accueillir les élèves', $texte);
    }

    public function test_les_trois_rappels_ont_des_textes_distincts(): void
    {
        $textes = [];
        foreach (['3_jours', 'jour_j', '3h_avant'] as $type) {
            $textes[] = $this->texte($this->envoyer($this->alice, new RappelCreneauNotification($this->item(), $type, '2026-10-02')));
        }

        $this->assertCount(3, array_unique($textes));
    }

    public function test_un_rappel_sans_description_n_affiche_pas_de_ligne_vide_ni_de_valeur_nulle(): void
    {
        $texte = $this->texte($this->envoyer($this->alice, new RappelCreneauNotification($this->item(['description' => '']), 'jour_j', '2026-10-02')));

        $this->assertStringNotContainsString('null', strtolower($texte));
        $this->assertStringContainsString('20:00 – 21:00', $texte);
    }

    // ══ Candidatures ══════════════════════════════════════════════════════

    public function test_le_message_aux_administrateurs_presente_le_candidat(): void
    {
        $candidat = Personne::factory()->enAttente()->create(['prenom' => 'Sofia', 'nom' => 'Benali', 'email' => 'sofia@example.test', 'telephone' => '06 12 34 56 78']);

        $mail = $this->envoyer($this->bilal, new NouveauMembreNotification($candidat));
        $texte = $this->texte($mail);

        $this->assertSame('bilal@example.test', $mail['to']);
        $this->assertSame('Nouvelle candidature — Sofia BENALI', $mail['subject']);
        $this->assertStringContainsString('Bonjour Bilal', $texte);
        $this->assertStringContainsString('Sofia BENALI', $texte);
        $this->assertStringContainsString('sofia@example.test', $texte);
        $this->assertStringContainsString('06 12 34 56 78', $texte);
        $this->assertStringContainsString(route('admin.candidatures.index'), $mail['html'], 'lien vers la liste des candidatures');
    }

    public function test_l_invitation_contient_le_lien_de_creation_du_mot_de_passe(): void
    {
        $url = 'https://planning.example.test/nouveau-mot-de-passe/JETON123?email=sofia%40example.test';
        $candidat = Personne::factory()->create(['prenom' => 'Sofia', 'email' => 'sofia@example.test']);

        $mail = $this->envoyer($candidat, new CandidatureValideeNotification($url));

        $this->assertSame('Bienvenue chez AMANA — Créez votre mot de passe', $mail['subject']);
        $this->assertStringContainsString('Cher Sofia', $this->texte($mail));
        $this->assertStringContainsString('https://planning.example.test/nouveau-mot-de-passe/JETON123', $mail['html']);
    }

    public function test_le_message_de_connexion_directe_contient_le_lien_de_connexion(): void
    {
        $candidat = Personne::factory()->create(['prenom' => 'Sofia', 'email' => 'sofia@example.test']);

        $mail = $this->envoyer($candidat, new CandidatureValideeDejaInscritNotification('https://planning.example.test/connexion'));

        $this->assertSame('Votre accès AMANA Planning est activé', $mail['subject']);
        $this->assertStringContainsString('Vous avez déjà un compte AMANA', $this->texte($mail));
        $this->assertStringContainsString('https://planning.example.test/connexion', $mail['html']);
    }

    // ══ Échanges ══════════════════════════════════════════════════════════

    public function test_la_demande_d_echange_presente_les_deux_creneaux_et_les_deux_liens(): void
    {
        $mail = $this->envoyer($this->bilal, new EchangeDemandeNotification($this->echange));
        $texte = $this->texte($mail);

        $this->assertSame('bilal@example.test', $mail['to']);
        $this->assertSame("Demande d'échange de créneau — AMANA Planning", $mail['subject']);
        $this->assertStringContainsString('Bonjour Bilal', $texte);
        $this->assertStringContainsString('Alice ALPHA vous propose d\'échanger vos créneaux', $texte);
        $this->assertStringContainsString('ven. 2 oct.', $texte, 'le créneau cédé');
        $this->assertStringContainsString('ven. 9 oct.', $texte, 'le créneau de la cible');
        $this->assertStringContainsString(route('echanges.accepter', $this->echange->token_accept), $mail['html']);
        $this->assertStringContainsString(route('echanges.refuser', $this->echange->token_refuse), $mail['html']);
    }

    public function test_la_demande_ne_contient_aucun_jeton_etranger_a_la_demande(): void
    {
        $autre = Echange::factory()->create();

        $html = $this->envoyer($this->bilal, new EchangeDemandeNotification($this->echange))['html'];

        $this->assertStringNotContainsString($autre->token_accept, $html);
        $this->assertStringNotContainsString($autre->token_refuse, $html);
    }

    public function test_la_confirmation_est_adaptee_a_chaque_partie(): void
    {
        $demandeur = $this->envoyer($this->alice, new EchangeAccepteNotification($this->echange, 'demandeur'));
        $cible = $this->envoyer($this->bilal, new EchangeAccepteNotification($this->echange, 'cible'));

        $this->assertSame('Échange de créneau confirmé — AMANA Planning', $demandeur['subject']);
        $this->assertStringContainsString('Bilal BRAVO a accepté votre demande d\'échange', $this->texte($demandeur));
        $this->assertStringContainsString('Vous aviez ven. 2 oct.', $this->texte($demandeur));
        $this->assertStringContainsString('Vous avez accepté la demande d\'échange de Alice ALPHA', $this->texte($cible));
        $this->assertStringContainsString('Vous aviez ven. 9 oct.', $this->texte($cible));
    }

    public function test_le_refus_precise_que_le_creneau_reste_inchange(): void
    {
        $mail = $this->envoyer($this->alice, new EchangeRefuseNotification($this->echange));

        $this->assertSame('Échange de créneau refusé — AMANA Planning', $mail['subject']);
        $this->assertStringContainsString('Bilal BRAVO n\'a pas pu accepter votre demande', $this->texte($mail));
        $this->assertStringContainsString('Votre créneau du 2 octobre 2026 (Entree) reste inchangé', $this->texte($mail));
    }

    public function test_l_annulation_previent_la_cible_que_son_planning_n_a_pas_change(): void
    {
        $mail = $this->envoyer($this->bilal, new EchangeAnnuleNotification($this->echange));

        $this->assertSame('Demande d\'échange annulée — AMANA Planning', $mail['subject']);
        $this->assertStringContainsString('Alice ALPHA a annulé sa demande d\'échange concernant votre créneau du 9 octobre 2026 (Entree)', $this->texte($mail));
        $this->assertStringContainsString('Votre planning n\'a pas été modifié', $this->texte($mail));
    }

    public function test_l_expiration_previent_le_demandeur(): void
    {
        $mail = $this->envoyer($this->alice, new EchangeExpireNotification($this->echange));

        $this->assertSame('Échange de créneau expiré — AMANA Planning', $mail['subject']);
        $this->assertStringContainsString('n\'a pas reçu de réponse avant la date du créneau (2 octobre 2026)', $this->texte($mail));
        $this->assertStringContainsString('votre assignation reste inchangée', $this->texte($mail));
    }

    // ══ Planning généré ═══════════════════════════════════════════════════

    /** Contexte type d'une génération manuelle (vendredi 2 → samedi 10 octobre 2026), sans tâche non assignée. */
    private function contextePlanning(array $surcharge = []): array
    {
        return array_replace([
            'declencheur' => PlanningGenereNotification::DECLENCHEUR_MANUEL,
            'detail' => null,
            'acteur' => 'Bilal Bravo',
            'debut' => Carbon::parse('2026-10-02'),
            'fin' => Carbon::parse('2026-10-10'),
            'jours_generes' => 4,
            'non_assignes' => 0,
            'quand' => Carbon::parse('2026-09-30 09:00:00', 'Europe/Paris'),
        ], $surcharge);
    }

    public function test_la_generation_manuelle_presente_la_periode_le_resultat_et_l_auteur(): void
    {
        $mail = $this->envoyer($this->alice, new PlanningGenereNotification($this->contextePlanning()));
        $texte = $this->texte($mail);

        $this->assertSame('alice@example.test', $mail['to']);
        $this->assertStringContainsString('Planning généré', $mail['subject']);
        $this->assertStringContainsString('2 oct.', $mail['subject']);
        $this->assertStringContainsString('10 oct. 2026', $mail['subject']);
        $this->assertStringContainsString('Cher(e) Alice', $texte);
        $this->assertStringContainsString("vient d'être généré depuis la page Planning › Générer", $texte);
        $this->assertStringContainsString('Vendredi 2 octobre → samedi 10 octobre 2026', $texte);
        $this->assertStringContainsString('4 jours générés', $texte);
        $this->assertStringContainsString('0 tâche non assignée', $texte);
        $this->assertStringContainsString('Bilal Bravo', $texte);
        $this->assertStringContainsString('30 septembre 2026 à 09:00', $texte);
        $this->assertStringContainsString(route('planning.index'), $mail['html'], 'bouton vers le planning');
    }

    public function test_sans_tache_non_assignee_aucun_avertissement_n_est_affiche(): void
    {
        $texte = $this->texte($this->envoyer($this->alice, new PlanningGenereNotification($this->contextePlanning())));

        $this->assertStringNotContainsString('sans personne assignée', $texte);
    }

    public function test_des_taches_non_assignees_declenchent_un_avertissement_au_pluriel(): void
    {
        $texte = $this->texte($this->envoyer($this->alice, new PlanningGenereNotification($this->contextePlanning(['non_assignes' => 3, 'jours_generes' => 1]))));

        $this->assertStringContainsString('1 jour généré', $texte);
        $this->assertStringContainsString('3 tâches non assignées', $texte);
        $this->assertStringContainsString('3 tâches sans personne assignée', $texte);
    }

    public function test_une_regeneration_automatique_indique_ce_qui_l_a_declenchee(): void
    {
        $mail = $this->envoyer($this->alice, new PlanningGenereNotification($this->contextePlanning([
            'declencheur' => PlanningGenereNotification::DECLENCHEUR_ABSENCE,
            'detail' => "l'absence de Awa Diallo (du 2 oct. 2026 au 5 oct. 2026)",
        ])));
        $texte = $this->texte($mail);

        $this->assertStringContainsString('Planning régénéré', $mail['subject']);
        $this->assertStringContainsString("a été régénéré automatiquement suite à l'absence de Awa Diallo (du 2 oct. 2026 au 5 oct. 2026).", $texte);
        $this->assertStringNotContainsString('depuis la page Planning', $texte);
    }

    public function test_sans_auteur_l_email_indique_une_action_automatique(): void
    {
        $texte = $this->texte($this->envoyer($this->alice, new PlanningGenereNotification($this->contextePlanning(['acteur' => null]))));

        $this->assertStringContainsString('Action automatique', $texte);
    }

    public function test_les_noms_saisis_du_planning_genere_sont_echappes(): void
    {
        $piege = '<script>alert(1)</script>';

        $mail = $this->envoyer($this->alice, new PlanningGenereNotification($this->contextePlanning([
            'declencheur' => PlanningGenereNotification::DECLENCHEUR_EVENEMENT,
            'detail' => "l'événement « Fête{$piege} »",
            'acteur' => "Bilal{$piege}",
        ])));

        $this->assertStringNotContainsString($piege, $mail['html']);
        $this->assertStringContainsString('&lt;script&gt;', $mail['html']);
    }

    // ══ Communs à tous les messages ═══════════════════════════════════════

    /** @return array<string, array{string}> */
    public static function tousLesMessages(): array
    {
        return array_combine(
            ['rappel', 'nouveau membre', 'invitation', 'connexion directe', 'demande', 'confirmation', 'refus', 'annulation', 'expiration', 'planning généré'],
            array_map(fn($c) => [$c], ['rappel', 'nouveau', 'invitation', 'directe', 'demande', 'confirmation', 'refus', 'annulation', 'expiration', 'planning']),
        );
    }

    private function messagePour(string $cle): array
    {
        $candidat = Personne::factory()->enAttente()->create(['prenom' => 'Sofia', 'nom' => 'Benali', 'email' => 'sofia@example.test', 'telephone' => '06 12 34 56 78']);

        return match ($cle) {
            'rappel' => $this->envoyer($this->alice, new RappelCreneauNotification($this->item(), 'jour_j', '2026-10-02')),
            'nouveau' => $this->envoyer($this->bilal, new NouveauMembreNotification($candidat)),
            'invitation' => $this->envoyer($candidat, new CandidatureValideeNotification('https://x.test/nouveau-mot-de-passe/T?email=s')),
            'directe' => $this->envoyer($candidat, new CandidatureValideeDejaInscritNotification('https://x.test/connexion')),
            'demande' => $this->envoyer($this->bilal, new EchangeDemandeNotification($this->echange)),
            'confirmation' => $this->envoyer($this->alice, new EchangeAccepteNotification($this->echange, 'demandeur')),
            'refus' => $this->envoyer($this->alice, new EchangeRefuseNotification($this->echange)),
            'annulation' => $this->envoyer($this->bilal, new EchangeAnnuleNotification($this->echange)),
            'expiration' => $this->envoyer($this->alice, new EchangeExpireNotification($this->echange)),
            'planning' => $this->envoyer($this->alice, new PlanningGenereNotification($this->contextePlanning(['non_assignes' => 2]))),
        };
    }

    #[DataProvider('tousLesMessages')]
    public function test_aucun_message_ne_laisse_de_syntaxe_de_gabarit_ni_de_valeur_vide(string $cle): void
    {
        $html = $this->messagePour($cle)['html'];

        $this->assertStringNotContainsString('{{', $html, 'variable Blade non interprétée');
        $this->assertStringNotContainsString('@if', $html);
        $this->assertStringNotContainsString('@foreach', $html);
        $this->assertStringNotContainsString('$echange', $html);
        $this->assertStringNotContainsString('$item', $html);
        $this->assertDoesNotMatchRegularExpression('/\b(null|undefined)\b/i', $this->texte(['html' => $html]), 'une valeur manquante rendue littéralement');
    }

    #[DataProvider('tousLesMessages')]
    public function test_chaque_message_porte_le_logo_en_piece_jointe_integree(string $cle): void
    {
        $this->assertStringContainsString('cid:', $this->messagePour($cle)['html']);
    }

    #[DataProvider('tousLesMessages')]
    public function test_chaque_message_est_adresse_a_un_seul_destinataire_avec_un_objet_non_vide(string $cle): void
    {
        $mail = $this->messagePour($cle);

        $this->assertMatchesRegularExpression('/^[^@\s]+@example\.test$/', $mail['to']);
        $this->assertNotSame('', trim($mail['subject']));
        $this->assertStringNotContainsString("\n", $mail['subject'], 'pas de saut de ligne dans l\'objet (injection d\'en-tête)');
    }

    // ══ Échappement des champs saisis par les utilisateurs ════════════════

    public function test_les_champs_saisis_sont_echappes_dans_les_messages(): void
    {
        $piege = '<script>alert(1)</script>';
        $this->alice->update(['prenom' => 'Al' . $piege, 'nom' => 'Alpha']);
        $this->bilal->update(['prenom' => 'Bi' . $piege]);
        $this->echange->refresh()->load(['demandeur', 'cible']);

        $rappel = $this->envoyer($this->alice, new RappelCreneauNotification($this->item(['nom' => 'Entrée' . $piege, 'description' => 'Texte' . $piege]), 'jour_j', '2026-10-02'));
        $demande = $this->envoyer($this->bilal->fresh(), new EchangeDemandeNotification($this->echange));
        $candidat = Personne::factory()->enAttente()->create(['prenom' => 'Sofia' . $piege, 'nom' => 'Benali', 'email' => 'sofia@example.test']);
        $nouveau = $this->envoyer($this->alice->fresh(), new NouveauMembreNotification($candidat));

        foreach ([$rappel, $demande, $nouveau] as $mail) {
            $this->assertStringNotContainsString($piege, $mail['html'], 'balise <script> non échappée dans le HTML du message');
        }
    }
}
