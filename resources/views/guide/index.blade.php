{{-- resources/views/guide/index.blade.php --}}
@extends('layouts.app')

@section('title', "Guide d'utilisation — AMANA")

@section('content')

{{--
    Guide d'utilisation — page unique avec sommaire ancré.
    Chaque section est conditionnée par le rôle exactement comme dans
    layouts/partials/sidebar.blade.php : un membre ne reçoit pas le HTML
    des sections Gestion / Administration (pas un simple masquage CSS).
--}}

{{-- En-tête --}}
<div class="mb-7">
    <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">Guide d'utilisation</h1>
    <p class="text-[13px] text-ink-muted mt-1">Tout ce qu'il faut savoir pour utiliser l'application AMANA Planning</p>
</div>

{{-- Sommaire --}}
<nav aria-label="Sommaire du guide"
    class="sticky top-4 z-10 bg-surface border border-surface-border rounded-xl shadow-sm px-4 py-3 mb-7 flex flex-wrap gap-x-4 gap-y-1.5">
    <a href="#planning" class="text-[12.5px] font-semibold text-accent hover:underline no-underline">📅 Planning</a>
    <a href="#mes-donnees" class="text-[12.5px] font-semibold text-accent hover:underline no-underline">🏖️ Mes données</a>
    <a href="#bilan" class="text-[12.5px] font-semibold text-accent hover:underline no-underline">🧾 Bilan</a>
    @if($user->isAdmin() || $user->isGestionnaire())
        <a href="#gestion" class="text-[12.5px] font-semibold text-accent hover:underline no-underline">⚙️ Gestion</a>
    @endif
    @if($user->isAdmin())
        <a href="#administration" class="text-[12.5px] font-semibold text-accent hover:underline no-underline">🛡️ Administration</a>
    @endif
</nav>

<div class="flex flex-col gap-7">

    {{-- ══════════════════════════════════════════════════════════════════
         SECTION : PLANNING — tous les utilisateurs connectés
    ══════════════════════════════════════════════════════════════════ --}}
    <section id="planning" class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-surface-3 bg-surface-2">
            <h2 class="font-heading text-[15px] font-semibold text-ink flex items-center gap-2">📅 Planning</h2>
        </div>
        <div class="px-5 py-5 flex flex-col gap-5 text-[13.5px] text-ink-light leading-relaxed">

            <div>
                <h3 class="font-heading text-[13.5px] font-semibold text-ink mb-1">Consulter le planning</h3>
                <p>Le menu <strong class="text-ink">Planning</strong> affiche les permanences du vendredi et du
                    samedi, semaine par semaine. Utilisez la barre de filtres pour n'afficher que certaines
                    années ou certains mois : cliquez sur une pastille pour l'activer ou la désactiver. Le
                    bouton <strong class="text-ink">✕ Effacer</strong> retire tous les filtres actifs, et
                    <strong class="text-ink">📚 Historique complet</strong> affiche également les semaines
                    passées.</p>
            </div>

            <div>
                <h3 class="font-heading text-[13.5px] font-semibold text-ink mb-1">Mon planning</h3>
                <p>La page <strong class="text-ink">🙋 Mon planning</strong> ne montre que les créneaux qui vous
                    concernent personnellement, pour un accès rapide sans avoir à filtrer la vue complète.</p>
            </div>

            <div>
                <h3 class="font-heading text-[13.5px] font-semibold text-ink mb-1">Demander un échange</h3>
                <p>Depuis <strong class="text-ink">🔄 Mes échanges</strong>, vous pouvez proposer l'échange
                    d'un créneau qui vous est assigné contre celui d'une autre personne. La personne visée
                    reçoit un e-mail avec un lien pour <strong class="text-ink">accepter</strong> ou
                    <strong class="text-ink">refuser</strong> la demande. Un badge rouge sur ce menu indique le
                    nombre d'échanges en attente vous concernant. Vous pouvez annuler une demande que vous avez
                    initiée tant qu'elle n'a pas été traitée.</p>
            </div>

            <div>
                <h3 class="font-heading text-[13.5px] font-semibold text-ink mb-1">Statistiques</h3>
                <p>La page <strong class="text-ink">📊 Statistiques</strong> présente une vue d'ensemble de la
                    répartition des tâches sur la période affichée.</p>
            </div>

            <div>
                <h3 class="font-heading text-[13.5px] font-semibold text-ink mb-1">Export PDF</h3>
                <p>Le bouton <strong class="text-ink">📄 Export PDF</strong> permet de générer un document
                    imprimable du planning pour une période donnée.</p>
            </div>

        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════════════
         SECTION : MES DONNÉES — tous les utilisateurs connectés
    ══════════════════════════════════════════════════════════════════ --}}
    <section id="mes-donnees" class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-surface-3 bg-surface-2">
            <h2 class="font-heading text-[15px] font-semibold text-ink flex items-center gap-2">🏖️ Mes données</h2>
        </div>
        <div class="px-5 py-5 flex flex-col gap-5 text-[13.5px] text-ink-light leading-relaxed">

            <div>
                <h3 class="font-heading text-[13.5px] font-semibold text-ink mb-1">Absences</h3>
                <p>Déclarez vos périodes d'indisponibilité depuis <strong class="text-ink">🏖️ Absences</strong>.
                    Vous voyez les absences de tout le monde (pour savoir qui est disponible), mais vous ne
                    pouvez ajouter ou supprimer que les vôtres. Si l'absence chevauche une date où vous êtes
                    déjà assigné(e) à une tâche future, le planning correspondant est automatiquement
                    régénéré pour vous remplacer.</p>
            </div>

            <div>
                <h3 class="font-heading text-[13.5px] font-semibold text-ink mb-1">Disponibilités</h3>
                <p>La page <strong class="text-ink">🔒 Disponibilités</strong> vous permet d'indiquer, pour
                    chaque tâche et chaque jour (vendredi/samedi), si vous pouvez ou non l'effectuer. Une case
                    cochée signifie que vous êtes disponible. Ces informations sont prises en compte lors de la
                    génération du planning.</p>
            </div>

        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════════════
         SECTION : BILAN — tous les utilisateurs connectés
    ══════════════════════════════════════════════════════════════════ --}}
    <section id="bilan" class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-surface-3 bg-surface-2">
            <h2 class="font-heading text-[15px] font-semibold text-ink flex items-center gap-2">🧾 Bilan</h2>
        </div>
        <div class="px-5 py-5 flex flex-col gap-5 text-[13.5px] text-ink-light leading-relaxed">

            <div>
                <h3 class="font-heading text-[13.5px] font-semibold text-ink mb-1">Saisie</h3>
                <p>La page <strong class="text-ink">🧾 Saisie</strong> permet de renseigner le bilan quotidien
                    d'une date : les montants <strong class="text-ink">Amana food</strong> et les
                    <strong class="text-ink">effectifs de présence</strong>. Ces deux groupes s'enregistrent
                    indépendamment via leurs boutons respectifs, ce qui permet à deux personnes de saisir en
                    même temps sans écraser le travail de l'autre. N'importe quel utilisateur connecté peut
                    consulter et modifier le bilan de n'importe quelle date.</p>
            </div>

            <div>
                <h3 class="font-heading text-[13.5px] font-semibold text-ink mb-1">Statistiques</h3>
                <p>La page <strong class="text-ink">📊 Statistiques</strong> du Bilan affiche l'évolution des
                    montants et des effectifs sur une période choisie, sous forme de graphiques.</p>
            </div>

        </div>
    </section>

    @if($user->isAdmin() || $user->isGestionnaire())
    {{-- ══════════════════════════════════════════════════════════════════
         SECTION : GESTION — gestionnaire + admin uniquement
    ══════════════════════════════════════════════════════════════════ --}}
    <section id="gestion" class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-surface-3 bg-surface-2">
            <h2 class="font-heading text-[15px] font-semibold text-ink flex items-center gap-2">⚙️ Gestion</h2>
            <p class="text-[11px] text-ink-muted mt-0.5">Visible par les gestionnaires et les administrateurs</p>
        </div>
        <div class="px-5 py-5 flex flex-col gap-5 text-[13.5px] text-ink-light leading-relaxed">

            <div>
                <h3 class="font-heading text-[13.5px] font-semibold text-ink mb-1">Générer un planning</h3>
                <p>Depuis <strong class="text-ink">✨ Générer</strong>, choisissez une période puis lancez la
                    génération : un aperçu vous est présenté avant validation définitive. Le moteur répartit
                    les tâches entre les personnes disponibles en tenant compte des restrictions, des absences
                    et de l'équilibrage des charges. En cas d'erreur après validation, un
                    <strong class="text-ink">rollback</strong> permet d'annuler la dernière génération.</p>
            </div>

            <div>
                <h3 class="font-heading text-[13.5px] font-semibold text-ink mb-1">Modifier le planning</h3>
                <p>Sur la page <strong class="text-ink">Planning</strong>, cliquez sur une cellule pour
                    assigner ou réassigner une personne à une tâche. Vous pouvez également ajouter un créneau
                    à une semaine, supprimer un créneau, ou annuler un cours (bouton
                    <strong class="text-ink">🚫 Annulation cours</strong>).</p>
            </div>

            <div>
                <h3 class="font-heading text-[13.5px] font-semibold text-ink mb-1">Événements</h3>
                <p>La page <strong class="text-ink">🎉 Événements</strong> permet de déclarer un événement
                    organisationnel sur une période. Si l'événement est bloquant, les tâches concernées sur les
                    créneaux déjà planifiés sont automatiquement désassignées et une bannière d'information
                    apparaît sur le planning.</p>
            </div>

            <div>
                <h3 class="font-heading text-[13.5px] font-semibold text-ink mb-1">Échanges (validation)</h3>
                <p>La page <strong class="text-ink">🔄 Échanges</strong> de cette section liste les demandes
                    d'échange en attente entre membres et permet de les <strong class="text-ink">approuver</strong>
                    ou de les <strong class="text-ink">refuser</strong>. Un badge indique le nombre de demandes
                    à traiter.</p>
            </div>

            @if(Route::has('settings.index'))
            <div>
                <h3 class="font-heading text-[13.5px] font-semibold text-ink mb-1">Paramètres</h3>
                <p>La page <strong class="text-ink">⚙️ Paramètres</strong> regroupe les réglages généraux de
                    l'application, comme l'ouverture des inscriptions.</p>
            </div>
            @endif

        </div>
    </section>
    @endif

    @if($user->isAdmin())
    {{-- ══════════════════════════════════════════════════════════════════
         SECTION : ADMINISTRATION — admin uniquement
    ══════════════════════════════════════════════════════════════════ --}}
    <section id="administration" class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-surface-3 bg-surface-2">
            <h2 class="font-heading text-[15px] font-semibold text-ink flex items-center gap-2">🛡️ Administration</h2>
            <p class="text-[11px] text-ink-muted mt-0.5">Visible par les administrateurs uniquement</p>
        </div>
        <div class="px-5 py-5 flex flex-col gap-5 text-[13.5px] text-ink-light leading-relaxed">

            <div>
                <h3 class="font-heading text-[13.5px] font-semibold text-ink mb-1">Personnes</h3>
                <p>La page <strong class="text-ink">👥 Personnes</strong> liste l'ensemble des membres de
                    l'association. Vous pouvez y créer une personne, modifier ses informations et son rôle
                    (membre, gestionnaire, admin), ou la supprimer.</p>
            </div>

            <div>
                <h3 class="font-heading text-[13.5px] font-semibold text-ink mb-1">Candidatures</h3>
                <p>La page <strong class="text-ink">📥 Candidatures</strong> liste les demandes d'inscription
                    en attente. Valider une candidature crée (ou active) le compte correspondant et envoie une
                    notification par e-mail à la personne. Un badge indique le nombre de candidatures à
                    traiter.</p>
            </div>

            <div>
                <h3 class="font-heading text-[13.5px] font-semibold text-ink mb-1">Diagnostic SMTP</h3>
                <p>La page <strong class="text-ink">🔧 Diagnostic SMTP</strong> permet de tester l'envoi
                    d'e-mails depuis l'application, pour vérifier que la configuration de messagerie
                    fonctionne correctement.</p>
            </div>

            <div>
                <h3 class="font-heading text-[13.5px] font-semibold text-ink mb-1">Statistiques d'activité</h3>
                <p>La page <strong class="text-ink">📈 Statistiques d'activité</strong> donne une vue
                    d'ensemble de l'utilisation de l'application (connexions, actions effectuées, etc.).</p>
            </div>

            <div>
                <h3 class="font-heading text-[13.5px] font-semibold text-ink mb-1">Journal d'audit</h3>
                <p>La page <strong class="text-ink">📜 Journal d'audit</strong> trace les actions sensibles
                    effectuées dans l'application (créations, modifications, suppressions), avec la date,
                    l'auteur et le détail de chaque action. Utilisez les filtres pour retrouver un événement
                    précis.</p>
            </div>

        </div>
    </section>
    @endif

</div>

@endsection
