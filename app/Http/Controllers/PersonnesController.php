<?php
// app/Http/Controllers/PersonnesController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use Amana\Shared\Services\AccountChangeNotifier;
use App\Http\Requests\Personnes\StorePersonneRequest;
use App\Http\Requests\Personnes\UpdatePersonneRequest;
use App\Models\Personne;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PersonnesController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService,
        private readonly AccountChangeNotifier $notifier,
    ) {}

    // ── CRUD ──────────────────────────────────────────────────────────────

    public function index(): View
    {
        $personnes = Personne::with([
            'roles' => function ($q) {
                // orderByRaw : garantit que roles->first() (utilisé dans
                // personnes/index.blade.php pour le badge de rôle) renvoie
                // toujours le rôle le plus élevé, même si une personne a
                // historiquement plusieurs lignes de rôle planning en base
                // (ex. données antérieures à RoleService::syncRolePlanning(),
                // qui lui garantit une seule ligne pour les changements
                // effectués depuis l'UI) — sans cet ordre explicite, l'ordre
                // renvoyé par défaut n'est pas garanti et peut faire
                // apparaître un rôle inférieur (ex. "Bénévole" pour un
                // "Membre").
                $q->whereHas('application', fn($q2) => $q2->where('code', 'planning'))
                    ->orderByRaw("FIELD(ref_roles.code, 'admin', 'gestionnaire', 'membre', 'benevole')");
            },
        ])
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();

        return view('personnes.index', compact('personnes'));
    }

    public function create(): View
    {
        $statuts = ['En attente', 'Validé', 'Suspendu', 'Archivé'];
        $roles = $this->roleService->planningRoles();

        return view('personnes.form', compact('statuts', 'roles'));
    }

    public function store(StorePersonneRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $roleCode = $data['role'];
        unset($data['role']);

        $personne = Personne::create($data);
        $this->roleService->syncRolePlanning($personne, $roleCode);

        audit('create', 'personnes', $personne->id, null, array_merge(
            $personne->toArray(),
            ['role' => $roleCode]
        ));

        return redirect()->route('personnes.index')
            ->with('success', "Personne « {$personne->prenom} {$personne->nom} » créée avec le rôle {$roleCode}. "
                . "Elle n'a pas encore de mot de passe : ouvrez sa fiche et cliquez sur « Envoyer un lien de réinitialisation ».");
    }

    public function edit(int $id): View
    {
        $personne = Personne::findOrFail($id);
        $statuts = ['En attente', 'Validé', 'Suspendu', 'Archivé'];
        $roles = $this->roleService->planningRoles();
        $currentRole = $this->roleService->currentRoleCode($personne);

        return view('personnes.form', compact('personne', 'statuts', 'roles', 'currentRole'));
    }

    public function update(UpdatePersonneRequest $request, int $id): RedirectResponse
    {
        $personne = Personne::findOrFail($id);
        $avant = $personne->toArray();

        // Capturé AVANT l'enregistrement : ensuite $personne->email est déjà la
        // nouvelle adresse, et l'ancienne doit recevoir l'alerte de sécurité.
        $ancienEmail = (string) $personne->email;

        $data = $request->validated();
        $roleCode = $data['role'];
        unset($data['role']);

        $personne->update($data);
        $this->roleService->syncRolePlanning($personne, $roleCode);

        audit('update', 'personnes', $personne->id, $avant, array_merge(
            $personne->fresh()->toArray(),
            ['role' => $roleCode]
        ));

        $retour = redirect()->route('personnes.index')
            ->with('success', "Personne « {$personne->prenom} {$personne->nom} » mise à jour.");

        // Un administrateur a changé l'adresse email : ancienne adresse ET
        // nouvelle adresse sont prévenues (uniquement si elle a réellement
        // changé). Un échec d'envoi ne défait jamais la modification : il est
        // journalisé par le notifier et signalé ici par un avertissement.
        // email_verified_at est volontairement laissé tel quel (la
        // vérification d'email n'est pas exigée dans cette application).
        if (mb_strtolower($ancienEmail) !== mb_strtolower((string) $personne->email)) {
            $prevenus = $this->notifier->emailChanged($personne, $ancienEmail, (string) $personne->email, parAdministrateur: true);

            if (!$prevenus) {
                $retour->with('warning', AccountChangeNotifier::AVERTISSEMENT_ECHEC);
            }
        }

        return $retour;
    }

    /**
     * « Envoyer un lien de réinitialisation » : envoie à la personne l'email
     * standard de « mot de passe oublié » (broker 'personnes'). L'administrateur
     * ne saisit ni ne voit jamais de mot de passe ni de jeton. Audité par le
     * notifier (acteur = admin connecté, cible = la personne, jamais le jeton) ;
     * limité à 5 envois par minute (voir routes/web.php).
     */
    public function envoyerLienReinitialisation(int $id): RedirectResponse
    {
        $personne = Personne::findOrFail($id);
        $nom = "{$personne->prenom} {$personne->nom}";

        return match ($this->notifier->sendResetLink($personne)) {
            Password::RESET_LINK_SENT => back()->with('success', "Lien de réinitialisation envoyé à {$personne->email} ({$nom})."),
            Password::RESET_THROTTLED => back()->with('warning', "Un lien vient déjà d'être envoyé à {$nom} : patientez une minute avant d'en renvoyer un."),
            default => back()->with('error', "L'envoi du lien de réinitialisation à {$nom} a échoué. Vérifiez la configuration email (Diagnostic SMTP)."),
        };
    }

    /**
     * Désactive une personne validée : bloque sa connexion (voir
     * Amana\Shared\Http\Controllers\AuthController::login(), qui refuse déjà
     * tout statut 'Suspendu') SANS toucher à son historique (créneaux,
     * absences, restrictions, rappels envoyés) ni à ses rôles planning —
     * ces derniers sont volontairement conservés en base pour être restaurés
     * tels quels par reactiver(), sans repasser par un choix de rôle.
     */
    public function desactiver(int $id): RedirectResponse
    {
        $personne = Personne::findOrFail($id);

        if ($personne->statut !== 'Validé') {
            return redirect()->route('personnes.index')
                ->with('error', 'Seule une personne au statut « Validé » peut être désactivée.');
        }

        $avant = $personne->toArray();
        $personne->statut = 'Suspendu';
        $personne->save();

        audit('update', 'personnes', $personne->id, $avant, $personne->fresh()->toArray());

        return redirect()->route('personnes.index')
            ->with('success', "Personne « {$personne->prenom} {$personne->nom} » désactivée. Elle ne peut plus se connecter tant qu'elle n'est pas réactivée.");
    }

    /**
     * Réactive une personne suspendue : remet son statut à 'Validé' sans
     * modifier date_debut_planning ni ses rôles (jamais touchés par
     * desactiver() ci-dessus), donc sa place dans la rotation planning est
     * restaurée à l'identique.
     */
    public function reactiver(int $id): RedirectResponse
    {
        $personne = Personne::findOrFail($id);

        if ($personne->statut !== 'Suspendu') {
            return redirect()->route('personnes.index')
                ->with('error', 'Seule une personne suspendue peut être réactivée.');
        }

        $avant = $personne->toArray();
        $personne->statut = 'Validé';
        $personne->save();

        audit('update', 'personnes', $personne->id, $avant, $personne->fresh()->toArray());

        return redirect()->route('personnes.index')
            ->with('success', "Personne « {$personne->prenom} {$personne->nom} » réactivée.");
    }

    /**
     * Suppression définitive — volontairement limitée aux statuts qui n'ont
     * jamais pu accumuler d'historique de planning (candidature jamais
     * validée, ou déjà archivée) : plan_creneaux_taches/plan_absences/
     * plan_restrictions/plan_rappels_envoyes référencent id_personne SANS
     * clé étrangère réelle (ref_personnes vit dans la connexion 'commun',
     * une FK inter-bases n'est pas possible), donc supprimer une personne
     * 'Validé' ou 'Suspendu' ayant déjà tourné dans le planning laisserait
     * des id_personne orphelins dans ces tables. Utiliser desactiver() pour
     * ces statuts — voir aussi PersonnesControllerTest.
     */
    public function destroy(int $id): RedirectResponse
    {
        $personne = Personne::findOrFail($id);

        if (!in_array($personne->statut, ['En attente', 'Archivé'], true)) {
            return redirect()->route('personnes.index')
                ->with('error', "Impossible de supprimer une personne au statut « {$personne->statut} » : désactivez-la plutôt, pour conserver son historique de planning.");
        }

        $avant = $personne->toArray();
        $nom = "{$personne->prenom} {$personne->nom}";

        $personne->delete();

        audit('delete', 'personnes', $id, $avant, null);

        return redirect()->route('personnes.index')
            ->with('success', "Personne « {$nom} » supprimée.");
    }
}
