<?php
// app/Http/Controllers/GuideController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Contrôleur du guide d'utilisation.
 *
 * Une seule page, dont les sections sont conditionnées par le rôle de
 * l'utilisateur connecté (membre / gestionnaire / admin), à l'identique
 * du gating utilisé dans layouts/partials/sidebar.blade.php. Un membre
 * ne reçoit tout simplement pas le HTML des sections Gestion/Administration
 * (pas juste masqué en CSS).
 */
class GuideController extends Controller
{
    public function index(): View
    {
        return view('guide.index', [
            'user' => Auth::user(),
        ]);
    }
}
