<?php
// app/Http/Middleware/ApplySessionPolicy.php
//
// Applique la durée de session configurée (voir App\Services\SessionPolicy).
// Ajouté au groupe `web`, APRÈS StartSession : il lit et écrit la session de la requête.
//
// État conservé dans la session (clé SESSION_KEY), posé à la connexion :
//   ['mode' => 'inactivite', 'derniere_activite' => <timestamp>]   case « Rester connecté » décochée
//   ['mode' => 'minuit',     'expire_a'          => <timestamp>]   case cochée : coupure au prochain minuit
//
// Pièges traités ici :
//   - /nav-badges est interrogé toutes les 45 s par la sidebar : s'il comptait comme
//     une activité, un onglet laissé ouvert ne ferait jamais expirer la session
//     (PASSIVE_ROUTES : on vérifie l'expiration mais on ne « rafraîchit » pas).
//   - Le cookie « se souvenir de moi » de Laravel (5 ans par défaut) reconnecterait
//     l'utilisateur à l'expiration de la session : à la connexion, sa durée est
//     ramenée au temps restant jusqu'à minuit, et une reconnexion par ce cookie sans
//     état de session connu (cookie antérieur à ce mécanisme) est refusée.
//   - Les sessions déjà ouvertes au déploiement n'ont pas d'état : elles démarrent
//     en mode « inactivite » à leur première requête.

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\SessionPolicy;
use Carbon\Carbon;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApplySessionPolicy
{
    public const SESSION_KEY = 'amana.session_policy';

    /** Routes de fond (polling) : ne comptent pas comme une activité de l'utilisateur. */
    public const PASSIVE_ROUTES = ['nav-badges.index'];

    public function __construct(private readonly SessionPolicy $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $connexion = $request->isMethod('POST') && $request->routeIs('login.submit');
        $resteConnecte = $connexion && $request->boolean('remember');

        if ($resteConnecte) {
            // Doit être posé AVANT Auth::attempt() (contrôleur partagé) : c'est lui qui
            // émet le cookie « se souvenir de moi ».
            $this->garde()->setRememberDuration($this->policy->minutesJusquAMinuit());
        }

        if (!$connexion && Auth::check()) {
            $expiree = $this->verifier($request);
            if ($expiree !== null) {
                return $expiree;
            }
        }

        $response = $next($request);

        if ($connexion && Auth::check()) {
            $request->session()->put(self::SESSION_KEY, $resteConnecte
                ? ['mode' => 'minuit', 'expire_a' => $this->policy->prochainMinuit()->getTimestamp()]
                : ['mode' => 'inactivite', 'derniere_activite' => Carbon::now()->getTimestamp()]);
        }

        return $response;
    }

    /** Renvoie la réponse de déconnexion si la session a expiré, sinon null (et met l'état à jour). */
    private function verifier(Request $request): ?Response
    {
        $session = $request->session();
        $etat = $session->get(self::SESSION_KEY);
        $maintenant = Carbon::now()->getTimestamp();

        if (!is_array($etat)) {
            // Reconnexion par le cookie « se souvenir de moi » sans état connu : cookie
            // émis avant ce mécanisme (durée de 5 ans) → on ne peut pas lui faire confiance.
            if ($this->garde()->viaRemember()) {
                return $this->expirer($request);
            }

            $session->put(self::SESSION_KEY, ['mode' => 'inactivite', 'derniere_activite' => $maintenant]);

            return null;
        }

        if (($etat['mode'] ?? null) === 'minuit') {
            return $maintenant >= (int) ($etat['expire_a'] ?? 0) ? $this->expirer($request) : null;
        }

        $delai = $this->policy->dureeInactiviteMinutes() * 60;
        $derniere = (int) ($etat['derniere_activite'] ?? $maintenant);

        if ($maintenant - $derniere > $delai) {
            return $this->expirer($request);
        }

        if (!$request->routeIs(...self::PASSIVE_ROUTES)) {
            $session->put(self::SESSION_KEY, ['mode' => 'inactivite', 'derniere_activite' => $maintenant]);
        }

        return null;
    }

    /** Le guard `web` est un SessionGuard (setRememberDuration()/viaRemember() n'existent pas sur le contrat Guard). */
    private function garde(): SessionGuard
    {
        /** @var SessionGuard $garde */
        $garde = Auth::guard();

        return $garde;
    }

    private function expirer(Request $request): Response
    {
        audit('logout', 'auth', null, null, ['motif' => 'session_expiree']);

        Auth::logout(); // oublie aussi le cookie « se souvenir de moi »
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => 'Votre session a expiré. Veuillez vous reconnecter.'], 401);
        }

        return redirect()->route('login')->with('error', 'Votre session a expiré. Veuillez vous reconnecter.');
    }
}
