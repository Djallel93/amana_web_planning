<?php
// app/Http/Controllers/CandidatureController.php
//
// Anciennement la moitié "métier" de AuthController.php : l'inscription
// publique (candidature) propre au planning. login()/logout()/forgot-password/
// reset-password ont déménagé dans Amana\Shared\Http\Controllers\AuthController
// (voir routes/web.php) — ce contrôleur ne garde que ce qui n'a pas
// d'équivalent générique.

declare(strict_types=1);

namespace App\Http\Controllers;

use Amana\Shared\Models\Personne;
use Amana\Shared\Models\Setting;
use App\Models\Restriction;
use App\Models\Tache;
use App\Notifications\NouveauMembreNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class CandidatureController extends Controller
{
    /**
     * Affiche le formulaire d'inscription publique.
     *
     * Si le paramètre `inscription_ouverte` est false dans ref_settings,
     * redirige vers la page de connexion avec un message informatif.
     * Par défaut (paramètre absent), les inscriptions sont considérées ouvertes.
     */
    public function showInscription(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('planning.index');
        }

        $ouvert = Setting::get('inscription_ouverte', 'planning') ?? true;
        if (!$ouvert) {
            return redirect()->route('login')
                ->with('error', 'Les inscriptions sont actuellement fermées. Veuillez contacter un administrateur.');
        }

        $taches = Tache::actif()->orderBy('id')->get();
        $jours = ['Vendredi', 'Samedi'];

        return view('auth.inscription', compact('taches', 'jours'));
    }

    /**
     * Traite la soumission du formulaire d'inscription publique.
     *
     * Vérifie à nouveau que les inscriptions sont ouvertes au moment de la
     * soumission pour éviter tout contournement via un formulaire mis en cache.
     */
    public function inscription(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('planning.index');
        }

        $ouvert = Setting::get('inscription_ouverte', 'planning') ?? true;
        if (!$ouvert) {
            return redirect()->route('login')
                ->with('error', 'Les inscriptions sont actuellement fermées.');
        }

        $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:commun.ref_personnes,email'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'restrictions' => ['nullable', 'array'],
        ], [
            'nom.required' => 'Le nom est obligatoire.',
            'prenom.required' => 'Le prénom est obligatoire.',
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'email.email' => 'Format d\'email invalide.',
        ]);

        // ── Créer la personne ──────────────────────────────────────────────
        $personne = Personne::create([
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'email' => $request->email,
            'telephone' => $request->telephone,
            'statut' => 'En attente',
        ]);

        // ── Enregistrer les restrictions ───────────────────────────────────
        $taches = Tache::actif()->get();
        $jours = ['Vendredi', 'Samedi'];
        $restrictionsPost = $request->input('restrictions', []);

        foreach ($taches as $tache) {
            foreach ($jours as $jour) {
                $autorise = isset($restrictionsPost[$tache->id][$jour]);

                Restriction::updateOrCreate(
                    ['id_personne' => $personne->id, 'id_tache' => $tache->id, 'jour' => $jour],
                    ['autorise' => $autorise]
                );
            }
        }

        // ── Recharger avec les relations nécessaires au template email ─────
        $personne->load(['restrictions.tache']);

        // ── Notifier tous les admins planning ─────────────────────────────
        $admins = Personne::adminsPlanning()->get();

        if ($admins->isNotEmpty()) {
            try {
                Notification::send($admins, new NouveauMembreNotification($personne));
                Log::info('[CandidatureController] Notification nouvelle candidature envoyée', [
                    'candidat_id' => $personne->id,
                    'nb_admins' => $admins->count(),
                ]);
            } catch (\Throwable $e) {
                Log::error('[CandidatureController] Échec notification nouvelle candidature', [
                    'candidat_id' => $personne->id,
                    'erreur' => $e->getMessage(),
                    'fichier' => $e->getFile() . ':' . $e->getLine(),
                ]);
            }
        }

        audit('create', 'inscription', $personne->id, null, [
            'email' => $personne->email,
            'statut' => 'En attente',
        ]);

        return redirect()->route('login')
            ->with('success', 'Votre candidature a bien été enregistrée. Un administrateur va l\'examiner et vous recevrez un email une fois votre compte activé.');
    }
}
