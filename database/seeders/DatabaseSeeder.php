<?php
// database/seeders/DatabaseSeeder.php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Personne;
use App\Models\Role;
use App\Models\Tache;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder principal — données de référence indispensables au module planning.
 * Idempotent : peut être relancé plusieurs fois sans créer de doublons.
 *
 * Ordre :
 *   1. Application 'planning'
 *   3. Tâches planifiables
 *   4. Rôles planning
 *   5. Compte administrateur
 *   6. Attribution du rôle admin
 *   7. Paramètres ref_settings
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Application planning ────────────────────────────────────────
        $planning = Application::firstOrCreate(
            ['code' => 'planning'],
            ['libelle' => 'AMANA Planning', 'actif' => true]
        );
        $this->command->info('✅ Application planning : OK');

        // ── 3. Tâches planifiables ─────────────────────────────────────────

        // Textes longs envoyés dans le body de l'événement Google Calendar —
        // source unique de vérité pour ces descriptions.
        $descCalRappelSandwich = <<<'TXT'
            🥪 PRÉPARER LES SANDWICHS

            👋 Tu es assigné à AMANA FOOD aujourd'hui.
            ⏰ Pense à préparer les sandwichs en avance !
            TXT;

        $descCalEntree = <<<'TXT'
            🚪 ENTRÉE

            📋 Responsabilités :
            • 👋 Accueillir les frères et indiquer la salle aux nouveaux
            • 🔓 Ouvrir la porte du bâtiment
            • 🚶 Éviter les regroupements dans les couloirs
            • 👀 Surveiller l'entrée
            • 🧹 Nettoyage / rangement des couloirs

            ⚠️ IMPORTANT : tu as également une tâche 🍽️ AMANA FOOD après le cours (vérifie ton calendrier).
            TXT;

        $descCalAssistanceAmanaFood = <<<'TXT'
            🤝 ASSISTANCE AMANA FOOD

            🍽️ Tu es assigné à l'ENTRÉE.
            ➡️ Tu dois également assister la personne à AMANA FOOD après le cours.
            TXT;

        $descCalMektaba = <<<'TXT'
            📚 MEKTABA

            📋 Responsabilités :
            • 👋 Accueillir les frères à l'entrée du local
            • 🛒 S'occuper des achats de la mektaba avant et après le cours
            • 👀 Surveiller la marchandise
            • 👥 Compter le nombre de présents lors de l'assise
            • 🧹 Nettoyage / rangement de la mektaba
            TXT;

        $descCalSalle = <<<'TXT'
            🏛️ SALLE

            📋 Responsabilités :
            • 🤝 Assister le frère Réda en cas de nécessité pendant et après l'assise
            • 🪑 Mise en place des tables pour les cours du week-end après l'assise
            • 🧹 Nettoyage / rangement de la salle
            TXT;

        $descCalAmanaFood = <<<'TXT'
            🍽️ AMANA FOOD

            📋 Responsabilités :
            • 🥪 Préparation des sandwichs / repas en amont du cours
            • 🍴 Tenir le stand AMANA FOOD après le cours
            • 🧹 Nettoyage / rangement du stand
            TXT;

        $descCalMessageBot = <<<'TXT'
            ℹ️ Salam ‘alaykoum !
            Nous mettons à votre disposition notre assistant virtuel sur Telegram pour permettre à ceux qui ne sont pas présents de poser leurs questions en lien direct avec les conférences du week-end.

            ⚠️ Merci de poser uniquement des questions liées au sujet de la conférence du jour.
            💬 Des sessions dédiées seront ouvertes plus tard pour les questions hors sujet (fiqh général, vie quotidienne, etc.).

            Pour poser votre question, suivez le lien ci-dessous :
            📲 https://t.me/AmanaQuestionsBot

            👉🏼 Les questions en lien avec la conférence sont regroupées et présentées à notre frère Réda, selon le temps disponible.

            Jazakum Allāhu khayran.

            https://t.me/AMANA_LIVE
            TXT;

        $taches = [
            // Tâches actives (rotation du scheduler)
            [
                'code' => 'entree',
                'libelle' => 'Entrée',
                'actif' => true,
                'description' => "Accueillir les frères à l'entrée, orienter les nouveaux vers la salle, surveiller les couloirs et le bâtiment. Inclut une tâche Amana Food après le cours.",
                'description_calendrier' => $descCalEntree,
            ],
            [
                'code' => 'mektaba',
                'libelle' => 'Mektaba',
                'actif' => true,
                'description' => "Accueillir les frères à l'entrée du local, gérer les achats et la marchandise de la mektaba, compter les présents et ranger après le cours.",
                'description_calendrier' => $descCalMektaba,
            ],
            [
                'code' => 'salle',
                'libelle' => 'Salle',
                'actif' => true,
                'description' => "Assister le frère Réda pendant et après l'assise, préparer la salle pour les cours du week-end et ranger après le cours.",
                'description_calendrier' => $descCalSalle,
            ],
            [
                'code' => 'amana_food',
                'libelle' => 'Amana Food',
                'actif' => true,
                'description' => 'Préparer les sandwichs/repas avant le cours, tenir le stand après le cours et ranger le stand.',
                'description_calendrier' => $descCalAmanaFood,
            ],
            [
                'code' => 'cours',
                'libelle' => 'Cours',
                'actif' => true,
                'description' => 'Animation du cours',
                'description_calendrier' => '',
            ],

            // Tâches inactives (webhook uniquement)
            [
                'code' => 'rappel_sandwich',
                'libelle' => 'Rappel Sandwich',
                'actif' => false,
                'description' => '',
                'description_calendrier' => $descCalRappelSandwich,
            ],
            [
                'code' => 'assistance_amana_food',
                'libelle' => 'Assistance Amana Food',
                'actif' => false,
                'description' => '',
                'description_calendrier' => $descCalAssistanceAmanaFood,
            ],
            [
                'code' => 'annonce_cours',
                'libelle' => 'Annonce Cours',
                'actif' => false,
                'description' => '',
                'description_calendrier' => '',
            ],
            [
                'code' => 'message_bot',
                'libelle' => 'Message Bot',
                'actif' => false,
                'description' => '',
                'description_calendrier' => $descCalMessageBot,
            ],
            [
                'code' => 'annulation_cours',
                'libelle' => 'Annulation Cours',
                'actif' => false,
                'description' => '',
                'description_calendrier' => '',
            ],
        ];

        foreach ($taches as $t) {
            Tache::updateOrCreate(
                ['code' => $t['code']],
                [
                    'libelle' => $t['libelle'],
                    'actif' => $t['actif'],
                    'description' => $t['description'],
                    'description_calendrier' => $t['description_calendrier'],
                ]
            );
        }
        $this->command->info('✅ Tâches insérées/mises à jour : ' . count($taches));

        // ── 4. Rôles planning ──────────────────────────────────────────────
        $roles = [
            ['code' => 'admin', 'libelle' => 'Administrateur'],
            ['code' => 'gestionnaire', 'libelle' => 'Gestionnaire'],
            ['code' => 'membre', 'libelle' => 'Membre officiel'],
            ['code' => 'benevole', 'libelle' => 'Bénévole'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['code' => $role['code'], 'id_application' => $planning->id],
                ['libelle' => $role['libelle']]
            );
        }
        $this->command->info('✅ Rôles insérés : ' . count($roles));

        // ── 5. Compte administrateur ───────────────────────────────────────
        $admin = Personne::updateOrCreate(
            ['email' => 'admin@amana.fr'],
            [
                'nom' => 'Admin',
                'prenom' => 'AMANA',
                'password' => Hash::make('changeme123!'),
                'email_verified_at' => now(),
                'statut' => 'Validé',
                'date_debut_planning' => now()->toDateString(),
            ]
        );
        $this->command->info('✅ Administrateur créé/mis à jour : admin@amana.fr');

        // ── 6. Attribution du rôle admin ───────────────────────────────────
        $roleAdmin = Role::where('code', 'admin')
            ->where('id_application', $planning->id)
            ->first();

        if ($roleAdmin) {
            $dejaAttribue = DB::table('ref_personnes_roles')
                ->where('id_personne', $admin->id)
                ->where('id_role', $roleAdmin->id)
                ->exists();

            if (!$dejaAttribue) {
                DB::table('ref_personnes_roles')->insert([
                    'id_personne' => $admin->id,
                    'id_role' => $roleAdmin->id,
                    'date_attribution' => now()->toDateString(),
                ]);
                $this->command->info('✅ Rôle admin attribué à admin@amana.fr');
            } else {
                $this->command->info('ℹ️  Rôle admin déjà attribué à admin@amana.fr');
            }
        }

        // ── 7. Paramètres ref_settings ─────────────────────────────────────
        $settings = [
            // ── A. Inscription ─────────────────────────────────────────────
            // Contrôle l'accès au formulaire public /inscription.
            // Modifiable uniquement par un administrateur via la page Paramètres.
            [
                'cle' => 'inscription_ouverte',
                'valeur' => '1',
                'type' => 'boolean',
                'libelle' => 'Inscriptions ouvertes',
                'description' => 'Active ou désactive le formulaire public d\'inscription (/inscription). Seuls les administrateurs peuvent modifier ce paramètre.',
            ],

            // ── B. Horaires & Lieu ─────────────────────────────────────────
            ['cle' => 'heure_cours', 'valeur' => '20:00', 'type' => 'time', 'libelle' => 'Heure du cours', 'description' => null],
            ['cle' => 'lieu', 'valeur' => '319 Rte de Vannes, 44800 Saint-Herblain, France', 'type' => 'string', 'libelle' => 'Lieu des permanences', 'description' => null],

            // ── C. Décalages horaires (offsets en minutes / heure du cours) ─
            ['cle' => 'offset_entree_debut', 'valeur' => '-30', 'type' => 'integer', 'libelle' => 'Entrée : début (min)', 'description' => "Créneau d'accueil à l'entrée : orientation des visiteurs et contrôle des accès."],
            ['cle' => 'offset_entree_fin', 'valeur' => '30', 'type' => 'integer', 'libelle' => 'Entrée : fin (min)', 'description' => "Créneau d'accueil à l'entrée : orientation des visiteurs et contrôle des accès."],
            ['cle' => 'offset_mektaba_debut', 'valeur' => '-20', 'type' => 'integer', 'libelle' => 'Mektaba : début (min)', 'description' => 'Tenue du stand Mektaba (vente de livres et publications).'],
            ['cle' => 'offset_mektaba_fin', 'valeur' => '100', 'type' => 'integer', 'libelle' => 'Mektaba : fin (min)', 'description' => 'Tenue du stand Mektaba (vente de livres et publications).'],
            ['cle' => 'offset_salle_debut', 'valeur' => '0', 'type' => 'integer', 'libelle' => 'Salle : début (min)', 'description' => 'Préparation et surveillance de la salle pendant la permanence.'],
            ['cle' => 'offset_salle_fin', 'valeur' => '90', 'type' => 'integer', 'libelle' => 'Salle : fin (min)', 'description' => 'Préparation et surveillance de la salle pendant la permanence.'],
            ['cle' => 'offset_amana_food_debut', 'valeur' => '30', 'type' => 'integer', 'libelle' => 'Amana Food : début (min)', 'description' => 'Distribution des repas Amana Food.'],
            ['cle' => 'offset_amana_food_fin', 'valeur' => '90', 'type' => 'integer', 'libelle' => 'Amana Food : fin (min)', 'description' => 'Distribution des repas Amana Food.'],
            ['cle' => 'offset_cours_debut', 'valeur' => '0', 'type' => 'integer', 'libelle' => 'Cours : début (min)', 'description' => 'Animation du cours hebdomadaire.'],
            ['cle' => 'offset_cours_fin', 'valeur' => '60', 'type' => 'integer', 'libelle' => 'Cours : fin (min)', 'description' => 'Animation du cours hebdomadaire.'],

            ['cle' => 'offset_rappel_sandwich_debut', 'valeur' => '0', 'type' => 'integer', 'libelle' => 'Rappel sandwich : début (ignoré, fixe 08:00)', 'description' => 'Rappel automatique envoyé le matin même pour la préparation des sandwichs (horaire fixe, indépendant de l\'heure du cours).'],
            ['cle' => 'offset_rappel_sandwich_fin', 'valeur' => '15', 'type' => 'integer', 'libelle' => 'Rappel sandwich : fin (ignoré, fixe 08:15)', 'description' => 'Rappel automatique envoyé le matin même pour la préparation des sandwichs (horaire fixe, indépendant de l\'heure du cours).'],
            ['cle' => 'offset_assistance_amana_food_debut', 'valeur' => '30', 'type' => 'integer', 'libelle' => 'Assistance Amana Food : début (min)', 'description' => 'Aide à la préparation et à la distribution, en soutien de la tâche Amana Food.'],
            ['cle' => 'offset_assistance_amana_food_fin', 'valeur' => '90', 'type' => 'integer', 'libelle' => 'Assistance Amana Food : fin (min)', 'description' => 'Aide à la préparation et à la distribution, en soutien de la tâche Amana Food.'],
            ['cle' => 'offset_annonce_cours_debut', 'valeur' => '-360', 'type' => 'integer', 'libelle' => 'Annonce cours : début (min)', 'description' => "Message d'annonce automatique du cours envoyé aux membres avant la permanence."],
            ['cle' => 'offset_annonce_cours_fin', 'valeur' => '-345', 'type' => 'integer', 'libelle' => 'Annonce cours : fin (min)', 'description' => "Message d'annonce automatique du cours envoyé aux membres avant la permanence."],
            ['cle' => 'offset_message_bot_debut', 'valeur' => '-30', 'type' => 'integer', 'libelle' => 'Message bot : début (min)', 'description' => 'Message automatique envoyé par le bot avant le début de la permanence.'],
            ['cle' => 'offset_message_bot_fin', 'valeur' => '0', 'type' => 'integer', 'libelle' => 'Message bot : fin (min)', 'description' => 'Message automatique envoyé par le bot avant le début de la permanence.'],
            ['cle' => 'offset_annulation_cours_debut', 'valeur' => '-360', 'type' => 'integer', 'libelle' => 'Annulation cours : début (min)', 'description' => 'Message d\'annulation automatique envoyé lorsque le cours est annulé pour cette date.'],
            ['cle' => 'offset_annulation_cours_fin', 'valeur' => '-345', 'type' => 'integer', 'libelle' => 'Annulation cours : fin (min)', 'description' => 'Message d\'annulation automatique envoyé lorsque le cours est annulé pour cette date.'],

            // ── D. Calendriers Google Calendar (identifiants) ───────────────
            // Chaque valeur est l'ID Google Calendar (calendarId, ex :
            // "xxxx@group.calendar.google.com") dans lequel l'événement sera
            // créé pour cette tâche/événement — résolu et sélectionné via le
            // dropdown de /parametres (alimenté par
            // GoogleCalendarService::listCalendars()), jamais saisi à la
            // main. Laissées vides ici : les vrais identifiants dépendent
            // des calendriers Google réels de l'environnement cible et
            // doivent être choisis après le premier déploiement, une fois
            // le compte de service partagé sur les calendriers concernés.
            // Une valeur vide = pas de synchronisation pour ce code (voir
            // WebhookPayloadBuilder::getCalendarIds()).
            ['cle' => 'calendar_entree', 'valeur' => '', 'type' => 'string', 'libelle' => 'Entrée', 'description' => null],
            ['cle' => 'calendar_mektaba', 'valeur' => '', 'type' => 'string', 'libelle' => 'Mektaba', 'description' => null],
            ['cle' => 'calendar_salle', 'valeur' => '', 'type' => 'string', 'libelle' => 'Salle', 'description' => null],
            ['cle' => 'calendar_amana_food', 'valeur' => '', 'type' => 'string', 'libelle' => 'Amana Food', 'description' => null],
            ['cle' => 'calendar_cours', 'valeur' => '', 'type' => 'string', 'libelle' => 'Cours', 'description' => null],
            ['cle' => 'calendar_rappel_sandwich', 'valeur' => '', 'type' => 'string', 'libelle' => 'Rappel Sandwich', 'description' => null],
            ['cle' => 'calendar_assistance_amana_food', 'valeur' => '', 'type' => 'string', 'libelle' => 'Assistance Amana Food', 'description' => null],
            ['cle' => 'calendar_annonce_cours', 'valeur' => '', 'type' => 'string', 'libelle' => 'Annonce Cours', 'description' => null],
            ['cle' => 'calendar_message_bot', 'valeur' => '', 'type' => 'string', 'libelle' => 'Message Bot', 'description' => null],
            ['cle' => 'calendar_annulation_cours', 'valeur' => '', 'type' => 'string', 'libelle' => 'Annulation Cours', 'description' => null],
            // Calendrier cible pour la synchronisation des absences (journée
            // entière, couleur Graphite fixe — voir WebhookAbsencePayloadBuilder).
            ['cle' => 'calendar_absence', 'valeur' => '', 'type' => 'string', 'libelle' => 'Absences', 'description' => 'Calendrier Google Calendar dans lequel les absences sont synchronisées (journée entière, couleur grise fixe). Laisser vide pour ne pas synchroniser les absences.'],

            // ── E. Couleurs Google Calendar par tâche/événement spécial ─────
            // colorId (1 à 11, voir GoogleCalendarColors::PALETTE), éditable
            // dans Paramètres → Couleurs. Valeurs par défaut alignées sur
            // GoogleCalendarColors::TACHES pour ne rien changer visuellement
            // tant que personne n'y touche.
            ['cle' => 'couleur_entree', 'valeur' => '7', 'type' => 'string', 'libelle' => 'Entrée', 'description' => 'Couleur Google Calendar (colorId 1-11) utilisée pour synchroniser cette tâche/cet événement.'],
            ['cle' => 'couleur_mektaba', 'valeur' => '10', 'type' => 'string', 'libelle' => 'Mektaba', 'description' => 'Couleur Google Calendar (colorId 1-11) utilisée pour synchroniser cette tâche/cet événement.'],
            ['cle' => 'couleur_salle', 'valeur' => '5', 'type' => 'string', 'libelle' => 'Salle', 'description' => 'Couleur Google Calendar (colorId 1-11) utilisée pour synchroniser cette tâche/cet événement.'],
            ['cle' => 'couleur_amana_food', 'valeur' => '11', 'type' => 'string', 'libelle' => 'Amana Food', 'description' => 'Couleur Google Calendar (colorId 1-11) utilisée pour synchroniser cette tâche/cet événement.'],
            ['cle' => 'couleur_cours', 'valeur' => '3', 'type' => 'string', 'libelle' => 'Cours', 'description' => 'Couleur Google Calendar (colorId 1-11) utilisée pour synchroniser cette tâche/cet événement.'],
            ['cle' => 'couleur_rappel_sandwich', 'valeur' => '6', 'type' => 'string', 'libelle' => 'Rappel Sandwich', 'description' => 'Couleur Google Calendar (colorId 1-11) utilisée pour synchroniser cette tâche/cet événement.'],
            ['cle' => 'couleur_assistance_amana_food', 'valeur' => '9', 'type' => 'string', 'libelle' => 'Assistance Amana Food', 'description' => 'Couleur Google Calendar (colorId 1-11) utilisée pour synchroniser cette tâche/cet événement.'],
            ['cle' => 'couleur_annonce_cours', 'valeur' => '8', 'type' => 'string', 'libelle' => 'Annonce Cours', 'description' => 'Couleur Google Calendar (colorId 1-11) utilisée pour synchroniser cette tâche/cet événement.'],
            ['cle' => 'couleur_message_bot', 'valeur' => '1', 'type' => 'string', 'libelle' => 'Message Bot', 'description' => 'Couleur Google Calendar (colorId 1-11) utilisée pour synchroniser cette tâche/cet événement.'],
            ['cle' => 'couleur_annulation_cours', 'valeur' => '4', 'type' => 'string', 'libelle' => 'Annulation Cours', 'description' => 'Couleur Google Calendar (colorId 1-11) utilisée pour synchroniser cette tâche/cet événement.'],
        ];

        foreach ($settings as $s) {
            DB::table('ref_settings')->updateOrInsert(
                ['id_application' => $planning->id, 'cle' => $s['cle']],
                [
                    'valeur' => $s['valeur'],
                    'type' => $s['type'],
                    'libelle' => $s['libelle'],
                    'description' => $s['description'] ?? null,
                ]
            );
        }
        $this->command->info('✅ Paramètres ref_settings insérés : ' . count($settings));

        // ── Récapitulatif ──────────────────────────────────────────────────
        $this->command->newLine();
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('  Connexion admin :');
        $this->command->info('  Email        : admin@amana.fr');
        $this->command->info('  Mot de passe : changeme123!');
        $this->command->warn('  ⚠️  Changez ce mot de passe en production !');
        $this->command->newLine();
        $this->command->warn('  ⚠️  COURS : configurer les restrictions via l\'UI');
        $this->command->warn('       Restrictions > cocher cours=true pour la personne');
        $this->command->warn('       désignée uniquement (tous les autres = false)');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }
}