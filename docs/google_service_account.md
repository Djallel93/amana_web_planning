# Obtenir `GOOGLE_SERVICE_ACCOUNT_JSON_BASE64`

Ce guide détaille, étape par étape, comment créer le compte de service Google Cloud utilisé par `GoogleCalendarService`, et comment produire la valeur à mettre dans `GOOGLE_SERVICE_ACCOUNT_JSON_BASE64`.

Aucun flux OAuth consentement n'est nécessaire : AMANA Planning est un outil interne mono-organisation, le compte de service s'authentifie directement avec sa propre clé.

---

## Vue d'ensemble

```txt
Google Cloud Console
  └─ Projet
       └─ Compte de service (identité applicative, pas un compte humain)
            └─ Clé JSON (téléchargée une seule fois, à conserver précieusement)
                 └─ base64 -w0 → GOOGLE_SERVICE_ACCOUNT_JSON_BASE64

Google Calendar (côté calendriers réels)
  └─ Chaque calendrier cible (AMANA - Planning, AMANA - Communications, AMANA - Événements…)
       └─ Partagé avec l'email du compte de service, droit "Apporter des modifications aux événements"

AMANA Planning (/parametres)
  └─ Chaque calendrier partagé ci-dessus DOIT en plus être enregistré manuellement
       (ID Google Calendar copié + collé) — voir étape 6bis, "Pourquoi une étape en plus".
```

Trois actions distinctes sont nécessaires : **créer** le compte de service côté Google Cloud, **partager** chaque calendrier avec lui côté Google Calendar, et **enregistrer** chaque calendrier dans l'application (`/parametres`). Oublier la deuxième étape est l'erreur la plus fréquente — l'authentification réussira, mais chaque appel API renverra une erreur 404 (calendrier invisible) sur les calendriers non partagés. La troisième étape est **obligatoire et non automatisable** : contrairement à ce qu'on pourrait attendre, partager un calendrier ne suffit pas à le rendre "visible" par une recherche automatique — voir l'explication à l'étape 6bis.

---

## 1. Créer ou choisir un projet Google Cloud

1. Rendez-vous sur [console.cloud.google.com](https://console.cloud.google.com/).
2. Connectez-vous avec un compte Google ayant accès à l'organisation AMANA (idéalement un compte administrateur, pas un compte personnel — voir la note sur la pérennité en fin de document).
3. En haut de la page, cliquez sur le sélecteur de projet (à côté du logo "Google Cloud") → **Nouveau projet**.
4. Donnez-lui un nom explicite, par exemple `amana-planning-calendar`.
5. Laissez l'organisation/emplacement par défaut sauf préférence contraire, puis **Créer**.
6. Une fois créé, sélectionnez-le dans le sélecteur de projet pour qu'il soit actif — toutes les étapes suivantes se déroulent **dans ce projet**.

> Si un projet Google Cloud existe déjà pour AMANA (par exemple utilisé pour d'autres intégrations), vous pouvez le réutiliser plutôt que d'en créer un nouveau — l'important est d'y activer l'API Calendar et d'y créer le compte de service ci-dessous.

---

## 2. Activer l'API Google Calendar

Le projet doit explicitement activer l'API avant de pouvoir l'utiliser.

1. Menu ☰ (en haut à gauche) → **API et services** → **Bibliothèque**.
2. Recherchez `Google Calendar API`.
3. Cliquez sur le résultat, puis sur **Activer**.

Sans cette étape, tous les appels échoueront avec une erreur du type `Calendar API has not been used in project ... before or it is disabled`.

---

## 3. Créer le compte de service

1. Menu ☰ → **API et services** → **Identifiants**.
2. Cliquez sur **+ Créer des identifiants** → **Compte de service**.
3. Renseignez :
    - **Nom du compte de service** : par exemple `amana-planning-sync`.
    - **ID du compte de service** : généré automatiquement à partir du nom (modifiable) — il constitue la première partie de l'email du compte, par exemple `amana-planning-sync@amana-planning-calendar.iam.gserviceaccount.com`.
    - **Description** (optionnel) : par exemple _"Synchronisation directe Google Calendar pour AMANA Planning (créneaux + événements organisationnels)"_.
4. Cliquez sur **Créer et continuer**.
5. À l'étape **"Accorder à ce compte de service l'accès au projet"** : **aucun rôle IAM n'est nécessaire**. L'accès aux calendriers se fait exclusivement via le partage individuel de chaque calendrier (étape 6 ci-dessous), pas via des rôles de projet Google Cloud. Cliquez sur **Continuer** sans rien sélectionner, puis **OK**/**Terminé**.

Vous arrivez sur la liste des comptes de service du projet. **Notez l'adresse email complète** du compte que vous venez de créer (colonne "Email") — c'est elle qu'il faudra utiliser à l'étape 6 pour partager les calendriers.

---

## 4. Générer la clé JSON

1. Toujours dans **API et services → Identifiants**, cliquez sur le compte de service que vous venez de créer.
2. Onglet **Clés**.
3. **Ajouter une clé** → **Créer une clé**.
4. Type de clé : **JSON** (sélectionné par défaut) → **Créer**.
5. Le fichier `.json` se télécharge automatiquement sur votre machine (nom du type `amana-planning-calendar-a1b2c3d4e5f6.json`).

> ⚠️ **Ce fichier ne peut être téléchargé qu'une seule fois.** Google n'en conserve pas de copie consultable a posteriori. S'il est perdu, il faudra générer une nouvelle clé (l'ancienne peut être révoquée séparément, voir section Sécurité).

Le contenu ressemble à ceci (valeurs tronquées) :

```json
{
    "type": "service_account",
    "project_id": "amana-planning-calendar",
    "private_key_id": "a1b2c3d4e5f6...",
    "private_key": "-----BEGIN PRIVATE KEY-----\nMIIEvQ...\n-----END PRIVATE KEY-----\n",
    "client_email": "amana-planning-sync@amana-planning-calendar.iam.gserviceaccount.com",
    "client_id": "123456789012345678901",
    "auth_uri": "https://accounts.google.com/o/oauth2/auth",
    "token_uri": "https://oauth2.googleapis.com/token",
    "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs",
    "client_x509_cert_url": "https://www.googleapis.com/robot/v1/metadata/x509/amana-planning-sync%40amana-planning-calendar.iam.gserviceaccount.com",
    "universe_domain": "googleapis.com"
}
```

Le champ `client_email` est le même que celui repéré à l'étape 3 — c'est l'adresse à partager sur les calendriers.

---

## 5. Traiter le fichier avec précaution

Dès le téléchargement :

- **Ne le committez jamais** dans le dépôt Git (même temporairement, même dans une branche). Vérifiez qu'aucun `.gitignore` ne l'oublie si vous le placez dans le répertoire du projet.
- Déplacez-le hors de tout dossier synchronisé publiquement (pas dans `~/Downloads` partagé, pas dans un Drive non chiffré partagé largement).
- Une fois la valeur base64 (étape 6) copiée dans le gestionnaire de secrets voulu (`.env` local + secret GitHub), **supprimez le fichier `.json` en clair** de votre machine (ou conservez-le uniquement dans un coffre-fort de mots de passe chiffré type Bitwarden/1Password, jamais en clair sur le disque).

---

## 6. Partager les calendriers cibles

C'est l'étape la plus souvent oubliée. **Sans elle, l'authentification fonctionne mais aucun calendrier n'est visible.**

Pour **chaque** calendrier Google Calendar utilisé par l'application (`AMANA - Planning`, `AMANA - Communications`, `AMANA - Événements`, ou tout autre calendrier configuré dans `/parametres` ou sur un événement organisationnel) :

1. Ouvrez [Google Calendar](https://calendar.google.com/) avec un compte ayant les droits **propriétaire** sur le calendrier concerné.
2. Dans le panneau de gauche, survolez le calendrier → cliquez sur le menu **⋮** → **Paramètres et partage**.
3. Section **Partager avec des personnes en particulier** → **Ajouter des personnes et des groupes**.
4. Collez l'adresse email du compte de service (`client_email` du JSON, ex. `amana-planning-sync@amana-planning-calendar.iam.gserviceaccount.com`).
5. Niveau d'autorisation : **Apporter des modifications aux événements** (le niveau minimal permettant `create`/`update`/`delete` — pas besoin de "Apporter des modifications et gérer le partage").
6. **Envoyer**.

Répétez pour chaque calendrier. Un compte de service n'a accès qu'aux calendriers explicitement partagés avec lui — c'est une caractéristique de sécurité, pas une limitation à contourner.

> ⚠️ Le partage seul **ne suffit pas** à faire apparaître le calendrier dans l'application. Passez à l'étape 6bis ci-dessous une fois le compte de service configuré (étape 8).

---

## 6bis. Enregistrer le calendrier dans l'application

**Cette étape est obligatoire et ne peut pas être automatisée.** Contrairement à un compte Google humain, un compte de service n'a pas de "liste de calendriers" consultable — partager un calendrier avec lui ne le fait apparaître nulle part automatiquement, ni côté Google, ni côté application. Google le documente lui-même :

> _"Sharing a calendar with a user no longer automatically inserts the calendar into their CalendarList."_
> — [developers.google.com/workspace/calendar/api/concepts/sharing](https://developers.google.com/workspace/calendar/api/concepts/sharing)

Concrètement, l'appel API qui permettrait de "lister tous mes calendriers" (`calendarList.list()`) renvoie systématiquement une liste **vide** pour un compte de service, même quand plusieurs calendriers lui sont bel et bien partagés et qu'il peut parfaitement les lire/écrire. C'est un comportement documenté et confirmé par de nombreux rapports d'implémentation (dont [Google Issue Tracker #148804709](https://issuetracker.google.com/issues/148804709)) — pas un bug de configuration de votre côté. La seule requête qui fonctionne de façon fiable est celle qui demande "ce compte de service a-t-il accès à CE calendrier précis (ID connu) ?" (`calendars.get(calendarId)`), jamais "à quels calendriers ce compte de service a-t-il accès ?".

AMANA Planning contourne cette limitation avec un **registre géré manuellement** (table `ref_calendriers_google`, page `/parametres`) : chaque calendrier partagé doit y être ajouté une fois, avec son ID Google Calendar. L'application vérifie l'accès (`calendars.get()`) au moment de l'ajout, puis sert cette liste (sans appel API) à tous les dropdowns de sélection de calendrier.

### Marche à suivre

1. **Récupérer l'ID du calendrier** — dans [Google Calendar](https://calendar.google.com/) :
    - Survolez le calendrier dans le panneau de gauche → menu **⋮** → **Paramètres et partage**.
    - Faites défiler jusqu'à **Intégrer l'agenda**.
    - Copiez la valeur **ID de l'agenda** (ressemble à `abc123def456@group.calendar.google.com`, ou simplement à une adresse Gmail pour un calendrier personnel).
2. **Ouvrir `/parametres`** dans AMANA Planning (connecté en tant que gestionnaire ou administrateur).
3. Dans la section **"Registre des calendriers Google Calendar"** (en haut de la page), remplir le formulaire d'ajout :
    - **Nom d'affichage** : un nom clair pour les bénévoles, ex. `AMANA - Planning`.
    - **Calendar ID** : la valeur copiée à l'étape 1.
4. Cliquer **Ajouter et vérifier**. L'application appelle immédiatement `calendars.get()` sur cet ID :
    - ✅ **Succès** → le calendrier apparaît dans le registre, disponible dans tous les dropdowns.
    - ❌ **Échec** → un message explique la cause (ID incorrect, calendrier non partagé, droits insuffisants) — voir la section Dépannage plus bas.
5. Répéter pour chaque calendrier utilisé par l'application.

Depuis le registre, chaque ligne offre aussi : **Vérifier** (revérifie l'accès à la demande, utile après un changement de partage côté Google Calendar), **Activer/Désactiver** (masque temporairement un calendrier des dropdowns sans le supprimer), **Retirer** (supprime l'entrée du registre — sans effet sur les événements déjà créés sur Google Calendar).

---

## 7. Encoder la clé en base64

La valeur finale à mettre dans `GOOGLE_SERVICE_ACCOUNT_JSON_BASE64` est **le contenu intégral du fichier JSON, encodé en base64, sur une seule ligne**.

### macOS / Linux

```bash
base64 -w0 votre-fichier-cle.json
```

> Sur macOS, `base64` (BSD) n'accepte pas `-w0` — utilisez plutôt :
>
> ```bash
> base64 -i votre-fichier-cle.json | tr -d '\n'
> ```

### Windows (PowerShell)

```powershell
[Convert]::ToBase64String([IO.File]::ReadAllBytes("votre-fichier-cle.json"))
```

### Windows (Git Bash / WSL)

```bash
base64 -w0 votre-fichier-cle.json
```

Dans tous les cas, le résultat est une longue chaîne de caractères **sans retour à la ligne** (`ey...` généralement, puisqu'un JSON encodé en base64 commence typiquement par `ey` — c'est l'encodage de `{"`). Copiez-la intégralement, sans espace ni saut de ligne ajouté.

---

## 8. Renseigner la variable

### En local (développement)

Dans `.env` :

```dotenv
GOOGLE_SERVICE_ACCOUNT_JSON_BASE64=eyJ0eXBlIjogInNlcnZpY2VfYWNjb3VudCIsICJwcm9qZWN0X2lkIjogIi4uLg==
```

(remplacez par la vraie valeur obtenue à l'étape 7 — celle-ci n'est qu'un exemple tronqué).

### En production (IONOS via GitHub Actions)

La variable n'est **jamais** écrite en clair dans le dépôt. Elle est injectée au moment du build via un secret GitHub :

1. Sur GitHub, allez dans le dépôt → **Settings** → **Secrets and variables** → **Actions**.
2. **New repository secret**.
3. Nom : `GOOGLE_SERVICE_ACCOUNT_JSON_BASE64`.
4. Valeur : la chaîne base64 obtenue à l'étape 7.
5. **Add secret**.

Le workflow `.github/workflows/deploy.yaml` référence déjà ce secret (`${{ secrets.GOOGLE_SERVICE_ACCOUNT_JSON_BASE64 }}`) et le substitue dans `.env.production.template` au moment du build — aucune autre action n'est requise côté workflow.

---

## 9. Vérifier que tout fonctionne

Une fois la variable renseignée (local ou déployé) **et** au moins un calendrier enregistré (étape 6bis) :

```bash
# Vérifie l'authentification + l'accès à chaque calendrier déjà enregistré dans /parametres
php artisan amana:tester-google-calendar

# Vérifie un ID précis sans avoir besoin de l'enregistrer d'abord
php artisan amana:tester-google-calendar --calendar-id=abc123@group.calendar.google.com

# Vérifie en plus un cycle complet création/modification/suppression
# sur un événement de test (supprimé automatiquement)
php artisan amana:tester-google-calendar --create
```

> Cette commande **ne liste pas** "tous les calendriers partagés" — voir l'étape 6bis pour pourquoi ce n'est pas possible. Elle vérifie l'accès à des calendriers déjà connus (registre et/ou `--calendar-id`).

Une sortie du type suivant confirme que tout est en ordre :

```txt
Étape 1/3 — Vérification de la configuration…
✓ GOOGLE_SERVICE_ACCOUNT_JSON_BASE64 est présente.
  Compte de service : amana-planning-sync@amana-planning-calendar.iam.gserviceaccount.com

Étape 2/3 — Vérification de l'accès aux calendriers connus…
  Calendar ID                                       Résultat                Détail
  abc123@group.calendar.google.com                  ✓ AMANA - Planning
  def456@group.calendar.google.com                  ✓ AMANA - Communications

✓ Configuration OK pour au moins un calendrier. Relancez avec --create pour tester un cycle complet…
```

Si un calendrier échoue (`✗ Échec`) alors que d'autres réussissent, le problème est spécifique à CE calendrier (mauvais ID, partage manquant pour celui-là précisément) — revenez aux étapes 6 et 6bis pour ce calendrier uniquement.

---

## Dépannage

| Symptôme                                                                                         | Cause probable                                                                                          | Solution                                                                                                                                     |
| ------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| `GOOGLE_SERVICE_ACCOUNT_JSON_BASE64 invalide (base64 ou JSON non décodable)`                     | Valeur copiée incomplète, retour à la ligne inséré, ou fichier corrompu                                 | Regénérer l'encodage base64 (étape 7), vérifier qu'aucun espace/retour à la ligne n'a été ajouté en collant la valeur                        |
| `Calendar API has not been used in project ... before or it is disabled`                         | API non activée                                                                                         | Étape 2                                                                                                                                      |
| **`calendarList.list()` renvoie une liste vide alors que des calendriers sont partagés**         | **Comportement normal et attendu pour un compte de service** — ce n'est pas une erreur de configuration | Ne pas chercher à "réparer" ceci : utiliser le registre `/parametres` (étape 6bis) à la place, qui ne dépend pas de `calendarList.list()`    |
| Échec (`✗`) à l'ajout d'un calendrier dans `/parametres`, ou dans `amana:tester-google-calendar` | ID de calendrier incorrect, OU calendrier non partagé avec l'adresse exacte du compte de service        | Revérifier l'ID copié (étape 6bis.1) et l'adresse email partagée (étape 6, comparer avec celle affichée par `amana:tester-google-calendar`)  |
| `404` lors de l'ajout/vérification d'un calendrier précis                                        | Ce calendrier spécifique n'est pas partagé (les autres peuvent l'être)                                  | Vérifier le partage pour CE calendrier en particulier (étape 6)                                                                              |
| `403 insufficientPermissions` lors d'une écriture (mais lecture/vérification OK)                 | Calendrier partagé en lecture seule ("Voir tous les détails des événements") plutôt qu'en modification  | Repasser le niveau d'autorisation à "Apporter des modifications aux événements" (étape 6, point 5)                                           |
| Un calendrier enregistré dans `/parametres` ne fonctionne plus du jour au lendemain              | Accès révoqué ou modifié côté Google Calendar après l'enregistrement                                    | Bouton **Vérifier** sur la ligne du registre pour confirmer/infirmer, puis ré-partager si nécessaire (étape 6)                               |
| Clé JSON perdue (fichier supprimé, jamais sauvegardée ailleurs)                                  | —                                                                                                       | Générer une nouvelle clé (étape 4) ; envisager de révoquer l'ancienne (voir Sécurité) — les deux peuvent coexister le temps de la transition |

---

## Sécurité et bonnes pratiques

- **Une clé = un secret critique.** Quiconque la possède peut lire/écrire sur tous les calendriers partagés avec le compte de service, sans mot de passe ni double authentification.
- **Rotation** : Google Cloud permet d'avoir plusieurs clés actives simultanément pour un même compte de service. Pour faire tourner la clé (bonne pratique périodique, ou après un doute de fuite) : générer une nouvelle clé (étape 4), mettre à jour le secret GitHub et le `.env` local, vérifier avec `amana:tester-google-calendar --create`, puis **seulement ensuite** révoquer l'ancienne clé dans **Identifiants → [compte de service] → Clés → 🗑 Supprimer** en face de l'ancienne.
- **Compte Google Cloud propriétaire du projet** : privilégiez un compte partagé/administratif de l'association plutôt qu'un compte personnel d'un bénévole, pour éviter de perdre l'accès au projet en cas de départ. Si ce n'est pas déjà le cas, ajoutez au moins une deuxième personne comme **Propriétaire** du projet Google Cloud (menu ☰ → IAM et administration → IAM → Accorder l'accès).
- **Portée minimale** : le compte de service n'a délibérément **aucun rôle IAM** au niveau du projet Google Cloud (étape 3.5) — son seul accès provient du partage individuel de calendriers (étape 6). Ne lui accordez pas de rôle IAM supplémentaire sans raison précise.
- **Ne jamais** coller la clé JSON en clair dans un ticket, un message Slack/Discord, ou un email non chiffré — toujours passer par le mécanisme de secret (GitHub Actions secret, gestionnaire de mots de passe).
