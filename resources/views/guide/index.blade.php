{{-- resources/views/guide/index.blade.php --}}
@extends('layouts.app')

@section('title', "Guide d'utilisation — AMANA")

@section('content')

    {{--
    Guide d'utilisation — page unique avec sommaire ancré.
    Chaque section est conditionnée par le rôle exactement comme dans
    layouts/partials/sidebar.blade.php : un membre ne reçoit pas le HTML
    des sections Gestion / Administration (pas un simple masquage CSS).

    Chaque point est un <details> (accordéon natif, pas de JS requis pour
        l'ouverture/fermeture) accompagné d'un exemple concret pour rendre la
        lecture plus vivante. Les sections apparaissent en fondu à l'arrivée
        sur la page (animation Tailwind 'fade-in-up' définie dans
        tailwind.config.js), avec un léger décalage entre elles.
        --}}

        {{-- En-tête --}}
        <div class="mb-7 animate-fade-in-up motion-reduce:animate-none">
            <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">Guide d'utilisation</h1>
            <p class="text-[13px] text-ink-muted mt-1">Tout ce qu'il faut savoir pour utiliser l'application AMANA
                Planning — cliquez sur une question pour la déplier</p>
        </div>

        {{-- Sommaire --}}
        <nav aria-label="Sommaire du guide" id="guideSommaire" class="sticky top-4 z-10 bg-surface border border-surface-border rounded-xl shadow-sm px-4 py-3 mb-7
                    flex flex-wrap gap-x-1.5 gap-y-1.5 animate-fade-in-up motion-reduce:animate-none">
            <a href="#planning" data-guide-link
                class="guide-nav-link text-[12.5px] font-semibold text-accent px-2.5 py-1 rounded-md transition-colors duration-200 no-underline hover:bg-accent/10">📅
                Planning</a>
            <a href="#mes-donnees" data-guide-link
                class="guide-nav-link text-[12.5px] font-semibold text-accent px-2.5 py-1 rounded-md transition-colors duration-200 no-underline hover:bg-accent/10">🏖️
                Mes données</a>
            <a href="#bilan" data-guide-link
                class="guide-nav-link text-[12.5px] font-semibold text-accent px-2.5 py-1 rounded-md transition-colors duration-200 no-underline hover:bg-accent/10">🧾
                Bilan</a>
            @if($user->isAdmin() || $user->isGestionnaire())
                <a href="#gestion" data-guide-link
                    class="guide-nav-link text-[12.5px] font-semibold text-accent px-2.5 py-1 rounded-md transition-colors duration-200 no-underline hover:bg-accent/10">⚙️
                    Gestion</a>
            @endif
            @if($user->isAdmin())
                <a href="#administration" data-guide-link
                    class="guide-nav-link text-[12.5px] font-semibold text-accent px-2.5 py-1 rounded-md transition-colors duration-200 no-underline hover:bg-accent/10">🛡️
                    Administration</a>
            @endif
        </nav>

        <div class="flex flex-col gap-7">

            {{-- ══════════════════════════════════════════════════════════════════
            SECTION : PLANNING — tous les utilisateurs connectés
            ══════════════════════════════════════════════════════════════════ --}}
            <section id="planning" class="scroll-mt-20 bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden
                        animate-fade-in-up motion-reduce:animate-none" style="animation-delay: 60ms">
                <div class="px-5 py-4 border-b border-surface-3 bg-gradient-to-r from-accent/10 to-transparent">
                    <h2 class="font-heading text-[15px] font-semibold text-ink flex items-center gap-2">
                        <span
                            class="w-7 h-7 rounded-full bg-accent/15 flex items-center justify-center text-[13px]">📅</span>
                        Planning
                    </h2>
                </div>
                <div class="px-5 py-4 flex flex-col gap-2.5">

                    <details open class="guide-item group">
                        <summary class="guide-summary">
                            <span class="guide-summary-icon bg-accent/10">🗓️</span>
                            <span class="flex-1">Consulter le planning</span>
                            <span class="guide-chevron">▾</span>
                        </summary>
                        <div class="guide-body">
                            <p>Le menu <strong class="text-ink">Planning</strong> affiche les permanences du vendredi et
                                du samedi, semaine par semaine. Utilisez la barre de filtres pour n'afficher que
                                certaines années ou certains mois : cliquez sur une pastille pour l'activer ou la
                                désactiver. Le bouton <strong class="text-ink">✕ Effacer</strong> retire tous les
                                filtres actifs, et <strong class="text-ink">📚 Historique complet</strong> affiche
                                également les semaines passées.</p>
                            <div class="guide-example">
                                <span class="guide-example-label">Exemple</span>
                                Vous voulez revoir uniquement les permanences d'<strong>août 2026</strong> : dans la
                                barre de filtres, décochez « Juillet » et laissez « Août » et « 2026 » cochés — la
                                grille ne garde que les semaines correspondantes.
                            </div>
                        </div>
                    </details>

                    <details class="guide-item group">
                        <summary class="guide-summary">
                            <span class="guide-summary-icon bg-accent/10">🙋</span>
                            <span class="flex-1">Mon planning</span>
                            <span class="guide-chevron">▾</span>
                        </summary>
                        <div class="guide-body">
                            <p>La page <strong class="text-ink">🙋 Mon planning</strong> ne montre que les créneaux qui
                                vous concernent personnellement, pour un accès rapide sans avoir à filtrer la vue
                                complète. Par défaut, seule la dernière année glissante (+ les créneaux à venir) est
                                affichée. Comme sur la page <strong class="text-ink">Planning</strong>, une barre de
                                filtres permet d'afficher uniquement certaines années ou certains mois — cliquez sur
                                une pastille pour l'activer ou la désactiver, et <strong class="text-ink">✕
                                    Effacer</strong> retire tous les filtres actifs. Le lien
                                <strong class="text-ink">📚 Historique complet</strong> recharge la page avec la
                                totalité de vos permanences passées, sans limite d'un an ; un lien
                                <strong class="text-ink">↩︎ Douze derniers mois</strong> apparaît alors pour revenir à
                                la vue par défaut.
                            </p>
                            <div class="guide-example">
                                <span class="guide-example-label">Exemple</span>
                                L'utilisateur A veut savoir rapidement s'il travaille ce week-end : il ouvre
                                <strong>Mon planning</strong> et voit immédiatement « Mektaba — samedi 18 juillet »,
                                sans avoir à chercher dans la grille complète. Plus tard, il veut retrouver un créneau
                                effectué il y a deux ans : il clique sur <strong>📚 Historique complet</strong> puis
                                utilise les pastilles d'année pour se limiter à l'année recherchée.
                            </div>
                        </div>
                    </details>

                    <details class="guide-item group">
                        <summary class="guide-summary">
                            <span class="guide-summary-icon bg-accent/10">🔄</span>
                            <span class="flex-1">Demander un échange</span>
                            <span class="guide-chevron">▾</span>
                        </summary>
                        <div class="guide-body">
                            <p>Depuis <strong class="text-ink">🔄 Mes échanges</strong>, vous pouvez proposer l'échange
                                d'un créneau qui vous est assigné contre celui d'une autre personne. La personne visée
                                reçoit un e-mail avec un lien pour <strong class="text-ink">accepter</strong> ou
                                <strong class="text-ink">refuser</strong> la demande. Un badge rouge sur ce menu
                                indique le nombre d'échanges en attente vous concernant. Vous pouvez annuler une
                                demande que vous avez initiée tant qu'elle n'a pas été traitée.
                            </p>
                            <div class="guide-example">
                                <span class="guide-example-label">Exemple</span>
                                L'utilisateur A est assigné à <strong>Entrée, vendredi 17 juillet</strong> mais part en
                                vacances ce jour-là. Il ouvre <strong>Mes échanges</strong>, sélectionne son créneau,
                                choisit l'utilisateur B comme destinataire et envoie la demande. L'utilisateur B reçoit
                                un e-mail, clique sur <strong>Accepter</strong> et le planning est mis à jour
                                automatiquement pour les deux.
                            </div>
                        </div>
                    </details>

                    <details class="guide-item group">
                        <summary class="guide-summary">
                            <span class="guide-summary-icon bg-accent/10">📊</span>
                            <span class="flex-1">Statistiques</span>
                            <span class="guide-chevron">▾</span>
                        </summary>
                        <div class="guide-body">
                            <p>La page <strong class="text-ink">📊 Statistiques</strong> présente une vue d'ensemble de
                                la répartition des tâches sur la période affichée.</p>
                            <div class="guide-example">
                                <span class="guide-example-label">Exemple</span>
                                En consultant les statistiques du trimestre, un gestionnaire remarque que l'utilisateur
                                A a effectué 8 créneaux « Entrée » contre 2 pour les autres bénévoles — de quoi
                                rééquilibrer la répartition lors de la prochaine génération.
                            </div>
                        </div>
                    </details>

                    <details class="guide-item group">
                        <summary class="guide-summary">
                            <span class="guide-summary-icon bg-accent/10">📄</span>
                            <span class="flex-1">Export PDF</span>
                            <span class="guide-chevron">▾</span>
                        </summary>
                        <div class="guide-body">
                            <p>Le bouton <strong class="text-ink">📄 Export PDF</strong> permet de générer un document
                                imprimable du planning pour une période donnée.</p>
                            <div class="guide-example">
                                <span class="guide-example-label">Exemple</span>
                                Un bénévole exporte le planning du mois en PDF pour le consulter hors ligne.
                            </div>
                        </div>
                    </details>

                </div>
            </section>

            {{-- ══════════════════════════════════════════════════════════════════
            SECTION : MES DONNÉES — tous les utilisateurs connectés
            ══════════════════════════════════════════════════════════════════ --}}
            <section id="mes-donnees" class="scroll-mt-20 bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden
                        animate-fade-in-up motion-reduce:animate-none" style="animation-delay: 120ms">
                <div class="px-5 py-4 border-b border-surface-3 bg-gradient-to-r from-accent/10 to-transparent">
                    <h2 class="font-heading text-[15px] font-semibold text-ink flex items-center gap-2">
                        <span
                            class="w-7 h-7 rounded-full bg-accent/15 flex items-center justify-center text-[13px]">🏖️</span>
                        Mes données
                    </h2>
                </div>
                <div class="px-5 py-4 flex flex-col gap-2.5">

                    <details open class="guide-item group">
                        <summary class="guide-summary">
                            <span class="guide-summary-icon bg-accent/10">🏖️</span>
                            <span class="flex-1">Absences</span>
                            <span class="guide-chevron">▾</span>
                        </summary>
                        <div class="guide-body">
                            <p>Déclarez vos périodes d'indisponibilité depuis <strong class="text-ink">🏖️
                                    Absences</strong>. Vous voyez les absences de tout le monde (pour savoir qui est
                                disponible), mais vous ne pouvez ajouter ou supprimer que les vôtres. Si l'absence
                                chevauche une date où vous êtes déjà assigné(e) à une tâche future, le planning
                                correspondant est automatiquement régénéré pour vous remplacer.</p>
                            <div class="guide-example">
                                <span class="guide-example-label">Exemple</span>
                                L'utilisateur A part en vacances du 1er au 15 août et était déjà assigné à
                                <strong>Salle, samedi 8 août</strong>. Il déclare son absence sur cette période : le
                                créneau du 8 août est automatiquement réattribué à quelqu'un d'autre.
                            </div>
                        </div>
                    </details>

                    <details class="guide-item group">
                        <summary class="guide-summary">
                            <span class="guide-summary-icon bg-accent/10">🔒</span>
                            <span class="flex-1">Disponibilités</span>
                            <span class="guide-chevron">▾</span>
                        </summary>
                        <div class="guide-body">
                            <p>La page <strong class="text-ink">🔒 Disponibilités</strong> vous permet d'indiquer, pour
                                chaque tâche et chaque jour (vendredi/samedi), si vous pouvez ou non l'effectuer. Une
                                case cochée signifie que vous êtes disponible. Ces informations sont prises en compte
                                lors de la génération du planning.</p>
                            <div class="guide-example">
                                <span class="guide-example-label">Exemple</span>
                                L'utilisateur A ne peut pas etre la 30 min avant le cours : il décoche « Entrée » pour le
                                vendredi et le samedi. Il ne sera plus jamais proposé sur cette tâche lors des
                                prochaines générations.
                            </div>
                        </div>
                    </details>

                </div>
            </section>

            {{-- ══════════════════════════════════════════════════════════════════
            SECTION : BILAN — tous les utilisateurs connectés
            ══════════════════════════════════════════════════════════════════ --}}
            <section id="bilan" class="scroll-mt-20 bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden
                        animate-fade-in-up motion-reduce:animate-none" style="animation-delay: 180ms">
                <div class="px-5 py-4 border-b border-surface-3 bg-gradient-to-r from-accent/10 to-transparent">
                    <h2 class="font-heading text-[15px] font-semibold text-ink flex items-center gap-2">
                        <span
                            class="w-7 h-7 rounded-full bg-accent/15 flex items-center justify-center text-[13px]">🧾</span>
                        Bilan
                    </h2>
                </div>
                <div class="px-5 py-4 flex flex-col gap-2.5">

                    <details open class="guide-item group">
                        <summary class="guide-summary">
                            <span class="guide-summary-icon bg-accent/10">🧾</span>
                            <span class="flex-1">Saisie</span>
                            <span class="guide-chevron">▾</span>
                        </summary>
                        <div class="guide-body">
                            <p>La page <strong class="text-ink">🧾 Saisie</strong> permet de renseigner le bilan
                                quotidien d'une date : les montants <strong class="text-ink">Amana food</strong> et les
                                <strong class="text-ink">effectifs de présence</strong>. Ces deux groupes
                                s'enregistrent indépendamment via leurs boutons respectifs, ce qui permet à deux
                                personnes de saisir en même temps sans écraser le travail de l'autre. N'importe quel
                                utilisateur connecté peut consulter et modifier le bilan de n'importe quelle date.
                            </p>
                            <div class="guide-example">
                                <span class="guide-example-label">Exemple</span>
                                Après la fin du cours, la personne responsable de <strong>Amana food</strong> saisit le
                                montant
                                de la recette et l'enregistre, pendant que la personne responsable de la
                                <strong>Mektaba</strong> saisit
                                de son côté <strong>« Effectifs : 85 personnes »</strong> — les deux enregistrements ne
                                s'écrasent pas l'un l'autre.
                            </div>
                        </div>
                    </details>

                    <details class="guide-item group">
                        <summary class="guide-summary">
                            <span class="guide-summary-icon bg-accent/10">📊</span>
                            <span class="flex-1">Statistiques</span>
                            <span class="guide-chevron">▾</span>
                        </summary>
                        <div class="guide-body">
                            <p>La page <strong class="text-ink">📊 Statistiques</strong> du Bilan affiche l'évolution
                                des montants et des effectifs sur une période choisie, sous forme de graphiques.</p>
                            <div class="guide-example">
                                <span class="guide-example-label">Exemple</span>
                                En comparant la période du Ramadan aux mois habituels, on constate que la
                                fréquentation moyenne est réduite — utile pour anticiper les besoins en
                                Amana food.
                            </div>
                        </div>
                    </details>

                </div>
            </section>

            @if($user->isAdmin() || $user->isGestionnaire())
                {{-- ══════════════════════════════════════════════════════════════════
                SECTION : GESTION — gestionnaire + admin uniquement
                ══════════════════════════════════════════════════════════════════ --}}
                <section id="gestion" class="scroll-mt-20 bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden
                                    animate-fade-in-up motion-reduce:animate-none" style="animation-delay: 240ms">
                    <div class="px-5 py-4 border-b border-surface-3 bg-gradient-to-r from-accent/10 to-transparent">
                        <h2 class="font-heading text-[15px] font-semibold text-ink flex items-center gap-2">
                            <span
                                class="w-7 h-7 rounded-full bg-accent/15 flex items-center justify-center text-[13px]">⚙️</span>
                            Gestion
                        </h2>
                        <p class="text-[11px] text-ink-muted mt-0.5 ml-9">Visible par les gestionnaires et les
                            administrateurs</p>
                    </div>
                    <div class="px-5 py-4 flex flex-col gap-2.5">

                        <details open class="guide-item group">
                            <summary class="guide-summary">
                                <span class="guide-summary-icon bg-accent/10">✨</span>
                                <span class="flex-1">Générer un planning</span>
                                <span class="guide-chevron">▾</span>
                            </summary>
                            <div class="guide-body">
                                <p>Depuis <strong class="text-ink">✨ Générer</strong>, choisissez une période puis lancez
                                    la génération : un aperçu vous est présenté avant validation définitive. Le moteur
                                    répartit les tâches entre les personnes disponibles en tenant compte des restrictions,
                                    des absences et de l'équilibrage des charges. En cas d'erreur après validation, un
                                    <strong class="text-ink">rollback</strong> permet d'annuler la dernière génération.
                                </p>
                                <div class="guide-example">
                                    <span class="guide-example-label">Exemple</span>
                                    Un gestionnaire génère le planning de septembre et remarque dans l'aperçu que
                                    l'utilisateur A est assigné deux week-ends de suite. Il annule, ajuste les
                                    restrictions de l'utilisateur A, puis relance la génération avant de valider.
                                </div>
                            </div>
                        </details>

                        <details class="guide-item group">
                            <summary class="guide-summary">
                                <span class="guide-summary-icon bg-accent/10">🖊️</span>
                                <span class="flex-1">Modifier le planning</span>
                                <span class="guide-chevron">▾</span>
                            </summary>
                            <div class="guide-body">
                                <p>Sur la page <strong class="text-ink">Planning</strong>, cliquez sur une cellule pour
                                    assigner ou réassigner une personne à une tâche. Vous pouvez également ajouter un
                                    créneau à une semaine, supprimer un créneau, ou annuler un cours (bouton
                                    <strong class="text-ink">🚫 Annulation cours</strong>).
                                </p>
                                <div class="guide-example">
                                    <span class="guide-example-label">Exemple</span>
                                    L'utilisateur A se propose au dernier moment pour remplacer un absent : le
                                    gestionnaire clique sur la cellule vide « Entrée — samedi » et lui assigne directement
                                    la tâche.
                                </div>
                            </div>
                        </details>

                        <details class="guide-item group">
                            <summary class="guide-summary">
                                <span class="guide-summary-icon bg-accent/10">🎉</span>
                                <span class="flex-1">Événements</span>
                                <span class="guide-chevron">▾</span>
                            </summary>
                            <div class="guide-body">
                                <p>La page <strong class="text-ink">🎉 Événements</strong> permet de déclarer un événement
                                    organisationnel sur une période. Si l'événement est bloquant, les tâches concernées sur
                                    les créneaux déjà planifiés sont automatiquement désassignées et une bannière
                                    d'information apparaît sur le planning.</p>
                                <div class="guide-example">
                                    <span class="guide-example-label">Exemple</span>
                                    L'Aïd al-Adha tombe un week-end : le gestionnaire crée un événement bloquant du 8 au 9
                                    août. Les créneaux déjà planifiés ce week-end sont désassignés automatiquement, avec
                                    une bannière rappelant qu'il faut les réattribuer après la fête.
                                </div>
                            </div>
                        </details>

                        <details class="guide-item group">
                            <summary class="guide-summary">
                                <span class="guide-summary-icon bg-accent/10">🔄</span>
                                <span class="flex-1">Échanges (validation)</span>
                                <span class="guide-chevron">▾</span>
                            </summary>
                            <div class="guide-body">
                                <p>La page <strong class="text-ink">🔄 Échanges</strong> de cette section liste les
                                    demandes d'échange en attente entre membres et permet de les
                                    <strong class="text-ink">approuver</strong> ou de les
                                    <strong class="text-ink">refuser</strong>. Un badge indique le nombre de demandes à
                                    traiter.
                                </p>
                                <div class="guide-example">
                                    <span class="guide-example-label">Exemple</span>
                                    Le gestionnaire voit la demande « Utilisateur A ↔ Utilisateur B » en attente, vérifie
                                    qu'aucun conflit de disponibilité n'existe, puis clique sur
                                    <strong>Approuver</strong>.
                                </div>
                            </div>
                        </details>

                        @if(Route::has('settings.index'))
                            <details class="guide-item group">
                                <summary class="guide-summary">
                                    <span class="guide-summary-icon bg-accent/10">⚙️</span>
                                    <span class="flex-1">Paramètres</span>
                                    <span class="guide-chevron">▾</span>
                                </summary>
                                <div class="guide-body">
                                    <p>La page <strong class="text-ink">⚙️ Paramètres</strong> regroupe les réglages généraux
                                        de l'application, comme l'ouverture des inscriptions.</p>
                                    <div class="guide-example">
                                        <span class="guide-example-label">Exemple</span>
                                        L'association est complète pour la rentrée : le gestionnaire désactive temporairement
                                        « Inscriptions ouvertes » depuis les Paramètres.
                                    </div>
                                </div>
                            </details>
                        @endif

                    </div>
                </section>
            @endif

            @if($user->isAdmin())
                {{-- ══════════════════════════════════════════════════════════════════
                SECTION : ADMINISTRATION — admin uniquement
                ══════════════════════════════════════════════════════════════════ --}}
                <section id="administration" class="scroll-mt-20 bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden
                                    animate-fade-in-up motion-reduce:animate-none" style="animation-delay: 300ms">
                    <div class="px-5 py-4 border-b border-surface-3 bg-gradient-to-r from-accent/10 to-transparent">
                        <h2 class="font-heading text-[15px] font-semibold text-ink flex items-center gap-2">
                            <span
                                class="w-7 h-7 rounded-full bg-accent/15 flex items-center justify-center text-[13px]">🛡️</span>
                            Administration
                        </h2>
                        <p class="text-[11px] text-ink-muted mt-0.5 ml-9">Visible par les administrateurs uniquement</p>
                    </div>
                    <div class="px-5 py-4 flex flex-col gap-2.5">

                        <details open class="guide-item group">
                            <summary class="guide-summary">
                                <span class="guide-summary-icon bg-accent/10">👥</span>
                                <span class="flex-1">Personnes</span>
                                <span class="guide-chevron">▾</span>
                            </summary>
                            <div class="guide-body">
                                <p>La page <strong class="text-ink">👥 Personnes</strong> liste l'ensemble des membres de
                                    l'association. Vous pouvez y créer une personne, modifier ses informations et son rôle
                                    (membre, gestionnaire, admin), ou la supprimer.</p>
                                <div class="guide-example">
                                    <span class="guide-example-label">Exemple</span>
                                    L'utilisateur A rejoint l'association comme bénévole : l'admin crée sa fiche dans
                                    <strong>Personnes</strong> avec le rôle « Membre ».
                                </div>
                            </div>
                        </details>

                        <details class="guide-item group">
                            <summary class="guide-summary">
                                <span class="guide-summary-icon bg-accent/10">📥</span>
                                <span class="flex-1">Candidatures</span>
                                <span class="guide-chevron">▾</span>
                            </summary>
                            <div class="guide-body">
                                <p>La page <strong class="text-ink">📥 Candidatures</strong> liste les demandes
                                    d'inscription en attente. Valider une candidature crée (ou active) le compte
                                    correspondant et envoie une notification par e-mail à la personne. Un badge indique le
                                    nombre de candidatures à traiter.</p>
                                <div class="guide-example">
                                    <span class="guide-example-label">Exemple</span>
                                    Trois nouvelles candidatures attendent validation : l'admin ouvre celle de
                                    l'utilisateur A, la valide — son compte est activé et il reçoit aussitôt un e-mail de
                                    confirmation.
                                </div>
                            </div>
                        </details>

                        <details class="guide-item group">
                            <summary class="guide-summary">
                                <span class="guide-summary-icon bg-accent/10">🔧</span>
                                <span class="flex-1">Diagnostic SMTP</span>
                                <span class="guide-chevron">▾</span>
                            </summary>
                            <div class="guide-body">
                                <p>La page <strong class="text-ink">🔧 Diagnostic SMTP</strong> permet de tester l'envoi
                                    d'e-mails depuis l'application, pour vérifier que la configuration de messagerie
                                    fonctionne correctement.</p>
                                <div class="guide-example">
                                    <span class="guide-example-label">Exemple</span>
                                    Des membres signalent ne pas recevoir les e-mails de candidature validée : l'admin
                                    lance un envoi de test depuis le Diagnostic SMTP avant de creuser plus loin.
                                </div>
                            </div>
                        </details>

                        <details class="guide-item group">
                            <summary class="guide-summary">
                                <span class="guide-summary-icon bg-accent/10">📈</span>
                                <span class="flex-1">Statistiques d'activité</span>
                                <span class="guide-chevron">▾</span>
                            </summary>
                            <div class="guide-body">
                                <p>La page <strong class="text-ink">📈 Statistiques d'activité</strong> donne une vue
                                    d'ensemble de l'utilisation de l'application (connexions, actions effectuées, etc.).</p>
                                <div class="guide-example">
                                    <span class="guide-example-label">Exemple</span>
                                    L'admin remarque que personne n'a utilisé la page Bilan depuis un mois et en profite
                                    pour rappeler l'équipe de saisie lors de la prochaine réunion.
                                </div>
                            </div>
                        </details>

                        <details class="guide-item group">
                            <summary class="guide-summary">
                                <span class="guide-summary-icon bg-accent/10">📜</span>
                                <span class="flex-1">Journal d'audit</span>
                                <span class="guide-chevron">▾</span>
                            </summary>
                            <div class="guide-body">
                                <p>La page <strong class="text-ink">📜 Journal d'audit</strong> trace les actions sensibles
                                    effectuées dans l'application (créations, modifications, suppressions), avec la date,
                                    l'auteur et le détail de chaque action. Utilisez les filtres pour retrouver un
                                    événement précis.</p>
                                <div class="guide-example">
                                    <span class="guide-example-label">Exemple</span>
                                    Un créneau a été réassigné sans explication : l'admin filtre le Journal d'audit par
                                    date et retrouve que c'est un gestionnaire qui a fait le changement la veille à
                                    14h32.
                                </div>
                            </div>
                        </details>

                    </div>
                </section>
            @endif

        </div>

        @push('scripts')
            <style>
                /* ── Styles locaux au guide d'utilisation ──────────────────────────
                               Regroupés ici plutôt qu'en classes utilitaires répétées sur chaque
                               <details> pour garder le Blade lisible malgré le nombre de blocs. */

                .guide-item {
                    border: 1px solid rgb(var(--color-surface-border));
                    border-radius: 10px;
                    background: rgb(var(--color-surface-2));
                    transition: box-shadow 0.2s ease, transform 0.2s ease, border-color 0.2s ease;
                }

                .guide-item:hover {
                    box-shadow: 0 4px 12px rgba(13, 17, 23, 0.08);
                    transform: translateY(-1px);
                    border-color: rgb(var(--color-surface-border) / 0.6);
                }

                .guide-summary {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    padding: 11px 14px;
                    cursor: pointer;
                    list-style: none;
                    font-size: 13.5px;
                    font-weight: 600;
                    color: rgb(var(--color-ink));
                    user-select: none;
                }

                .guide-summary::-webkit-details-marker {
                    display: none;
                }

                .guide-summary-icon {
                    width: 26px;
                    height: 26px;
                    border-radius: 999px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 13px;
                    flex-shrink: 0;
                }

                .guide-chevron {
                    color: rgb(var(--color-ink-muted));
                    font-size: 12px;
                    transition: transform 0.25s ease;
                    flex-shrink: 0;
                }

                .guide-item[open] .guide-chevron {
                    transform: rotate(180deg);
                }

                .guide-body {
                    padding: 0 14px 14px 50px;
                    font-size: 13.5px;
                    color: rgb(var(--color-ink-light));
                    line-height: 1.6;
                    animation: guideBodyIn 0.25s ease-out both;
                }

                @keyframes guideBodyIn {
                    0% {
                        opacity: 0;
                        transform: translateY(-4px);
                    }

                    100% {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                .guide-example {
                    margin-top: 10px;
                    background: rgb(16 185 129 / 0.08);
                    border-left: 3px solid rgb(16 185 129 / 0.6);
                    border-radius: 6px;
                    padding: 9px 12px;
                    font-size: 13px;
                    color: rgb(var(--color-ink-light));
                }

                .guide-example-label {
                    display: inline-block;
                    font-size: 10px;
                    font-weight: 800;
                    text-transform: uppercase;
                    letter-spacing: 0.6px;
                    color: rgb(5 150 105);
                    margin-right: 6px;
                }

                /* Sommaire : lien actif mis en avant pendant le défilement (voir JS) */
                .guide-nav-link.is-active {
                    background: rgb(var(--color-accent) / 0.12);
                }

                @media (prefers-reduced-motion: reduce) {

                    .guide-item,
                    .guide-item:hover,
                    .guide-chevron,
                    .guide-body {
                        transition: none !important;
                        animation: none !important;
                        transform: none !important;
                    }
                }
            </style>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    // Défilement doux au clic sur un lien du sommaire, sans dépendre
                    // de "scroll-behavior: smooth" global (qui affecterait tout le site).
                    document.querySelectorAll('#guideSommaire [data-guide-link]').forEach(function (link) {
                        link.addEventListener('click', function (event) {
                            var target = document.querySelector(link.getAttribute('href'));
                            if (!target) return;
                            event.preventDefault();
                            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        });
                    });

                    // Mise en évidence du lien du sommaire correspondant à la section
                    // actuellement visible à l'écran.
                    var navLinks = Array.from(document.querySelectorAll('#guideSommaire [data-guide-link]'));
                    var sections = navLinks
                        .map(function (link) { return document.querySelector(link.getAttribute('href')); })
                        .filter(Boolean);

                    if ('IntersectionObserver' in window && sections.length) {
                        var observer = new IntersectionObserver(function (entries) {
                            entries.forEach(function (entry) {
                                var link = navLinks.find(function (l) {
                                    return l.getAttribute('href') === '#' + entry.target.id;
                                });
                                if (!link) return;
                                if (entry.isIntersecting) {
                                    navLinks.forEach(function (l) { l.classList.remove('is-active'); });
                                    link.classList.add('is-active');
                                }
                            });
                        }, { rootMargin: '-15% 0px -70% 0px' });

                        sections.forEach(function (section) { observer.observe(section); });
                    }
                });
            </script>
        @endpush

@endsection