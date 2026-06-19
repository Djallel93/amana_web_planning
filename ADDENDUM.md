# Addendum README — Nouvelles fonctionnalités

## Synchronisation Google Calendar pour les événements

Sur le formulaire de création/modification d'un événement (`/evenements/creer`,
`/evenements/{id}/editer`), un champ optionnel **« Nom du calendrier Google Calendar »**
a été ajouté.

- Si renseigné, un webhook Make.com est dispatché à chaque création, modification
  ou suppression de l'événement.
- Si vide, aucun webhook n'est envoyé pour cet événement (comportement inchangé).
- Stocké dans `ref_evenements.calendar_name` — propre à chaque événement (contrairement
  aux clés `calendar_*` de `ref_settings` qui sont propres aux tâches du planning).

### Format du payload

```json
{
  "type": "evenement",
  "action": "upsert",
  "genere_le": "2026-06-18T10:00:00+02:00",
  "evenement": {
    "id": 12,
    "nom": "Ramadan",
    "date_debut": "2025-03-01",
    "date_fin": "2025-03-30",
    "description": "...",
    "calendar_name": "AMANA - Événements",
    "taches_bloquees": ["amana_food", "entree"],
    "informatif": false
  }
}
```

Pour une suppression, `action` devient `"delete"` et `evenement` ne contient que
`id`, `nom`, `date_debut`, `date_fin`, `calendar_name` (suffisant pour que Make.com
retrouve et supprime l'événement Google Calendar correspondant).

> Make.com doit être configuré pour distinguer ce payload (clé `evenement`) du
> payload de planning existant (clé `creneaux`).

---

## Échanges de créneaux entre membres

### Vue d'ensemble

Un membre peut désormais demander à échanger un de ses créneaux futurs avec celui
d'un autre membre assigné à la même tâche. Le flux complet :

1. **A** ouvre « Mon planning », clique sur **🔄 Échanger** sur un créneau futur.
2. La modale liste tous les créneaux futurs d'autres membres sur la **même tâche**.
3. A sélectionne un créneau cible (de **B**) et envoie la demande.
4. **B** reçoit un email avec deux liens : **Accepter** / **Refuser**.
5. Si B accepte → l'échange est exécuté immédiatement, les deux plannings sont
   mis à jour, les deux parties reçoivent un email de confirmation.
6. Si B refuse → A est notifié, rien ne change.
7. Un **gestionnaire ou admin** peut à tout moment approuver ou refuser une
   demande en attente depuis `/admin/echanges` — cela **prend le pas** sur
   l'attente de la réponse de B.
8. Si aucune réponse n'est reçue avant la **date du créneau de A**, la demande
   expire automatiquement (commande planifiée quotidienne) et A est notifié.
9. Les liens des emails sont **à usage unique** — une fois cliqués, le statut
   passe à un état terminal et le lien ne peut plus être réutilisé. Pour échanger
   à nouveau (y compris swap retour), il faut repasser par une nouvelle demande.

### Nouvelle table : `plan_echanges`

| Colonne | Description |
|---|---|
| `id_personne_demandeur` / `id_creneau_demandeur` / `id_tache_demandeur` | Slot de A |
| `id_personne_cible` / `id_creneau_cible` / `id_tache_cible` | Slot de B |
| `statut` | `en_attente`, `accepte`, `refuse`, `expire`, `annule` |
| `token_accept` / `token_refuse` | Tokens à usage unique pour les liens email |
| `expires_at` | Date du créneau de A — passé cette date, la demande expire |
| `approuve_par` | ID de l'admin/gestionnaire si approuvé manuellement |

### Commande planifiée

```bash
php artisan amana:expire-echanges
```

Enregistrée dans `routes/console.php` via `Schedule::command(...)->dailyAt('01:00')`.
Nécessite que le **scheduler Laravel** tourne (cron `* * * * * php artisan schedule:run`
sur le serveur).

### Routes ajoutées

| Méthode | URL | Description |
|---|---|---|
| GET | `/echanges` | Liste des échanges du membre connecté |
| GET | `/echanges/slots-disponibles` | AJAX — slots échangeables |
| POST | `/echanges` | Créer une demande d'échange |
| DELETE | `/echanges/{id}` | Annuler sa propre demande |
| GET | `/echanges/{token}/accepter` | Lien email — accepter (public) |
| GET | `/echanges/{token}/refuser` | Lien email — refuser (public) |
| GET | `/admin/echanges` | Liste admin/gestionnaire |
| POST | `/admin/echanges/{id}/approuver` | Approuver (override) |
| POST | `/admin/echanges/{id}/refuser` | Refuser (override) |

### Ce qui n'est PAS couvert (hors scope, feature distincte)

- Le cas où B n'a **aucun créneau futur généré** pour la même tâche : il n'y a
  alors rien à échanger. Une fonctionnalité de **priorité de réassignation**
  pour la prochaine génération automatique serait une feature séparée.

### Étapes de déploiement

```bash
php artisan migrate
php artisan route:clear && php artisan route:cache   # si route caching utilisé
php artisan config:clear && php artisan config:cache # si config caching utilisé
```

Vérifier que le scheduler tourne :

```bash
crontab -l
# doit contenir :
# * * * * * cd /chemin/vers/app && php artisan schedule:run >> /dev/null 2>&1
```
