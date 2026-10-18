# Analyse du Système d'Abonnement aux Alertes — AquaSecure

## Résumé exécutif

Le système d'abonnement aux alertes est **entièrement fonctionnel** et bien architecturé. Les citoyens s'abonnent depuis leur espace citoyen (`/alertes`), **pas** depuis le back-office `/back/alert-subscriptions`. Le back-office est une vue **lecture seule** réservée aux gestionnaires et admins pour consulter les abonnements existants.

---

## 1. Architecture globale

```
Citoyen (rôle: citoyen)
  └── /alertes  [GET/POST/DELETE]  →  Front\AlertSubscriptionController
        ├── index()   → vue front.citizen.subscriptions
        ├── store()   → crée ou met à jour l'abonnement (updateOrCreate)
        └── destroy() → supprime l'abonnement (ownership vérifié)

Gestionnaire / Admin (rôles: gestionnaire, admin)
  └── /back/alert-subscriptions  [GET]  →  Back\AlertSubscriptionController
        └── index()   → vue back.drought.subscriptions.index (lecture seule, filtrable par zone)
```

---

## 2. Modèle de données — `AlertSubscription`

**Fichier :** `app/Models/AlertSubscription.php`

### Champs
| Champ        | Type      | Description                                  |
|--------------|-----------|----------------------------------------------|
| `id`         | bigint    | Clé primaire                                 |
| `user_id`    | FK        | Référence vers `users.id`                    |
| `zone_id`    | FK        | Référence vers `zones.id`                    |
| `type_alerte`| string    | Type d'alerte (voir constante TYPES)         |
| `actif`      | boolean   | Si l'abonnement est actif                    |
| `created_at` | timestamp | Date de création                             |
| `updated_at` | timestamp | Date de mise à jour                          |

### Relations
- `user()` → `BelongsTo(User::class)` — le citoyen abonné
- `zone()` → `BelongsTo(Zone::class)` — la zone surveillée

### Types d'alertes disponibles (`AlertSubscription::TYPES`)
| Clé           | Libellé                  |
|---------------|--------------------------|
| `tous`        | Toutes les alertes       |
| `qualite`     | Qualité de l'eau         |
| `restriction` | Restrictions             |
| `coupure`     | Coupures programmées     |

---

## 3. Flux utilisateur — Comment un citoyen s'abonne

### Étape 1 — Inscription / Connexion
Le citoyen doit posséder un compte avec le rôle `citoyen` (valeur : `'citoyen'`). Les rôles `gestionnaire` et `admin` ne peuvent pas accéder aux routes citoyennes (middleware `role:citoyen`).

### Étape 2 — Accès à la page d'abonnement
- URL directe : **`/alertes`**
- Depuis le dashboard citoyen (`/mon-espace`) → section « Mes abonnements »
- La page affiche un formulaire + la liste des zones déjà suivies

### Étape 3 — Formulaire d'abonnement (`/alertes` POST)
Le citoyen sélectionne :
1. **Une zone** (liste déroulante, toutes les zones disponibles)
2. **Un type d'alerte** (tous / qualité / restriction / coupure)

Envoi → `Front\AlertSubscriptionController::store()`

### Étape 4 — Logique `updateOrCreate`
Le contrôleur utilise `updateOrCreate` avec la clé composite `(user_id, zone_id)` :
- Si l'abonnement existe déjà pour cette zone → mise à jour du `type_alerte` et `actif = true`
- Sinon → création d'un nouvel abonnement

> **Note :** Un citoyen ne peut avoir qu'**un seul abonnement par zone** (unicité `user_id + zone_id`). Pour changer le type d'alerte d'une zone déjà suivie, il re-soumet le formulaire.

### Étape 5 — Consultation / Suppression
- Les zones suivies sont listées sur la même page `/alertes`
- Bouton « Se désabonner » → `DELETE /alertes/{subscription}` avec vérification d'ownership (`abort_unless($subscription->user_id === $request->user()->id, 403)`)

---

## 4. Routes détaillées

### Routes citoyennes (middleware: `auth`, `verified`, `role:citoyen`)
| Méthode | URI                        | Nom de route                    | Action                        |
|---------|----------------------------|---------------------------------|-------------------------------|
| GET     | `/alertes`                 | `citizen.subscriptions.index`   | Affiche la page               |
| POST    | `/alertes`                 | `citizen.subscriptions.store`   | Crée/met à jour l'abonnement  |
| DELETE  | `/alertes/{subscription}`  | `citizen.subscriptions.destroy` | Supprime l'abonnement         |

### Routes back-office (middleware: `auth`, `verified`, `role:gestionnaire,admin`)
| Méthode | URI                         | Nom de route                    | Action                           |
|---------|-----------------------------|---------------------------------|----------------------------------|
| GET     | `/back/alert-subscriptions` | `back.alert-subscriptions.index`| Liste tous les abonnements (r/o) |

---

## 5. Autorisations et sécurité

### Middleware `EnsureUserRole` (`app/Http/Middleware/EnsureUserRole.php`)
Compare `$user->role->value` (champ server-side, jamais transmis par le navigateur) avec les rôles autorisés. Retourne HTTP 403 si non autorisé.

### Contrôle d'ownership
Le `destroy()` front vérifie explicitement que `$subscription->user_id === $request->user()->id` avant de supprimer — un citoyen ne peut pas supprimer l'abonnement d'un autre.

### Validation (`StoreAlertSubscriptionRequest`)
- `zone_id` : requis, entier, doit exister dans `zones.id`
- `type_alerte` : requis, doit être dans `array_keys(AlertSubscription::TYPES)`

---

## 6. Intégration dans le dashboard citoyen

Le `CitizenDashboardController` utilise les abonnements pour personnaliser le dashboard :
1. Récupère toutes les zones suivies par l'utilisateur
2. Filtre les alertes qualité actives dans ces zones
3. Filtre les restrictions actives dans ces zones (ou globales)
4. Filtre les coupures planifiées/en cours dans ces zones

L'abonnement pilote donc directement ce que le citoyen voit sur son tableau de bord.

---

## 7. Vues

### `resources/views/front/citizen/subscriptions.blade.php`
- Extends `front.layouts.app`
- Grille 1/3 (formulaire) + 2/3 (liste des abonnements)
- Formulaire POST avec CSRF
- Liste avec bouton « Se désabonner » (form DELETE)
- Support dark mode (classes Tailwind)

### `resources/views/back/drought/subscriptions/index.blade.php`
- Extends `back.layouts.app`
- Filtre par zone via GET
- Tableau : Citoyen, Zone, Type d'alerte, État (Actif/Inactif), Date
- Pagination (25 par page)
- Vue **lecture seule** — aucun bouton d'action admin

---

## 8. Réponse directe à la question utilisateur

> **« Comment un citoyen peut faire un abonnement ? »**

Un citoyen s'abonne en :
1. Se connectant avec un compte de rôle `citoyen`
2. Allant sur **`/alertes`** (ou depuis son espace `/mon-espace`)
3. Sélectionnant une zone et un type d'alerte dans le formulaire
4. Cliquant « Enregistrer l'abonnement »

**La page `/back/alert-subscriptions` n'est PAS destinée aux citoyens** — c'est une vue de consultation pour les gestionnaires/admins, qui ne permet aucune action (pas de formulaire, pas de boutons de suppression).

---

## 9. Points d'amélioration potentiels

| # | Observation | Impact | Suggestion |
|---|-------------|--------|------------|
| 1 | Un citoyen ne peut avoir qu'un type d'alerte par zone (la clé composite écrase le précédent) | Moyen | Permettre plusieurs types par zone, ou l'indiquer clairement dans l'UI |
| 2 | Pas de notification par e-mail — la vue le signale explicitement | Faible | Envisager des notifications si la fonctionnalité est attendue |
| 3 | Le champ `actif` n'est jamais mis à `false` côté back-office | Faible | Ajouter la possibilité de désactiver un abonnement depuis le back-office |
| 4 | Pas de tri ou pagination côté citoyen | Faible | Si un citoyen a beaucoup d'abonnements, ajouter pagination |
| 5 | Pas de lien vers `/alertes` visible depuis la navigation publique | Moyen | Ajouter un lien dans le menu authentifié pour les citoyens |

---

## 10. Résumé des fichiers impliqués

| Fichier | Rôle |
|---------|------|
| `app/Models/AlertSubscription.php` | Modèle Eloquent, constante TYPES, relations |
| `app/Http/Controllers/Front/AlertSubscriptionController.php` | CRUD citoyen (index, store, destroy) |
| `app/Http/Controllers/Back/AlertSubscriptionController.php` | Consultation gestionnaire (index read-only) |
| `app/Http/Controllers/Front/CitizenDashboardController.php` | Utilise les abonnements pour personnaliser le dashboard |
| `app/Http/Requests/StoreAlertSubscriptionRequest.php` | Validation du formulaire d'abonnement |
| `app/Http/Middleware/EnsureUserRole.php` | Contrôle d'accès par rôle |
| `resources/views/front/citizen/subscriptions.blade.php` | Vue citoyen (formulaire + liste) |
| `resources/views/back/drought/subscriptions/index.blade.php` | Vue back-office (tableau lecture seule) |
| `routes/web.php` | Définition de toutes les routes |
