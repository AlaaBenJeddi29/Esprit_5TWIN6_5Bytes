# Analyse de la Gestion 4 : Sécheresse, Consommation et Ressources — Aquasecure

**Version :** 1.0  
**Date :** 2026-01-15  
**Responsable domaine :** Gestion 4 — Sécheresse, consommation et ressources  
**Statut :** Analyse complète

---

## 1. Vue d'ensemble de l'architecture actuelle

### Présentation générale
La **Gestion 4** du projet Aquasecure est un module décentralisé de gestion de la sécheresse, de la consommation d'eau et de la continuité du service. Elle couvre :
- **Mesure des ressources** : niveaux de barrages, nappes phréatiques, réservoirs
- **Suivi de la consommation** : relevés mensuels par zone avec prévisions
- **Restrictions d'usage** : types d'alerte (vigilance, alerte, alerte renforcée, crise)
- **Continuité du service** : coupures programmées planifiées par infrastructures

### Langage et frameworks
- **Framework** : Laravel 12 (PHP 8.2+)
- **Accès aux données** : ORM Eloquent avec migrations
- **Interface** : Blade templates + front-end Tailwind CSS
- **Contrôle d'accès** : Rôles (Citoyen, Gestionnaire, Admin)

### Principes architecturaux observés
1. **Séparation Backend/Frontend** : Espace Citoyen (front.citizen.*) vs Espace Gestionnaire (back.*)
2. **Service métier dédié** : `DroughtAnalyzer` pour calculs complexes (prévisions, scores de risque)
3. **Validation centralisée** : Requêtes HTTP typées (StoreXxxRequest, UpdateXxxRequest)
4. **Modèles enrichis** : Labels et états calculés au niveau du modèle (p. ex. `typeLabel()`, `niveauStatut()`)

---

## 2. Modèles de données et schéma

### 2.1. Table `water_levels` — Niveaux des ressources

**Migrations :** `2026_10_17_120011_create_water_levels_table.php`

**Champs :**
| Champ | Type | Contrainte | Description |
|-------|------|-----------|-------------|
| id | BIGINT UNSIGNED | PK | Identifiant unique |
| nom_source | VARCHAR(150) | NOT NULL | Nom du barrage, réservoir, rivière (ex. "Barrage du Mellah") |
| zone_id | BIGINT UNSIGNED | FK, nullable | Référence à la zone (zones.id) |
| type_source | VARCHAR(30) | DEFAULT 'barrage' | Type : `barrage`, `nappe`, `reservoir`, `riviere` |
| niveau | DECIMAL(5,2) | NOT NULL | Niveau en pourcentage ou unité (0-100 en %) |
| unite | VARCHAR(10) | DEFAULT '%' | Unité de mesure |
| seuil_alerte | DECIMAL(5,2) | DEFAULT 30 | Niveau d'alerte (en %) |
| seuil_critique | DECIMAL(5,2) | DEFAULT 15 | Niveau critique (en %) |
| date_mesure | DATETIME | NOT NULL | Date/heure de la mesure |
| created_at | TIMESTAMP | | Créé à |
| updated_at | TIMESTAMP | | Modifié à |

**Index :** `(zone_id, date_mesure)`  
**Relations :** `Zone` (nombreux à un)

**Enums/États :**
- Niveau (calculé) : `critique`, `alerte`, `normal`
- Type : barrage, nappe, réservoir, rivière

**Modèle :** `App\Models\Drought\WaterLevel`

---

### 2.2. Table `consumption_readings` — Relevés de consommation

**Migration :** `2026_10_17_120012_create_consumption_readings_table.php`

**Champs :**
| Champ | Type | Contrainte | Description |
|-------|------|-----------|-------------|
| id | BIGINT UNSIGNED | PK | Identifiant unique |
| zone_id | BIGINT UNSIGNED | FK, required | Référence à la zone (zones.id, cascade delete) |
| periode | DATE | NOT NULL | Mois de référence (toujours le 1er du mois) |
| consommation_m3 | DECIMAL(15,2) | NOT NULL | Consommation en m³ |
| nombre_abonnes | UNSIGNED INT | nullable | Nombre d'abonnés servis cette période |
| created_at | TIMESTAMP | | Créé à |
| updated_at | TIMESTAMP | | Modifié à |

**Unique constraint :** `(zone_id, periode)`  
**Relations :** `Zone` (nombreux à un)

**Modèle :** `App\Models\Drought\ConsumptionReading`

**Note :** Les données historiques remontent sur 12 mois. Un relevé par zone par mois.

---

### 2.3. Table `restrictions` — Restrictions d'usage

**Migration :** `2026_10_17_120013_create_restrictions_table.php`

**Champs :**
| Champ | Type | Contrainte | Description |
|-------|------|-----------|-------------|
| id | BIGINT UNSIGNED | PK | Identifiant unique |
| zone_id | BIGINT UNSIGNED | FK, nullable | Zone impactée ; NULL = toutes zones |
| type | VARCHAR(30) | DEFAULT 'vigilance' | Type : `vigilance`, `alerte`, `alerte_renforcee`, `crise` |
| titre | VARCHAR(180) | NOT NULL | Titre court de la restriction (ex. "Restriction hivernale levée") |
| description | TEXT | nullable | Description détaillée (contexte, durée estimée, etc.) |
| usages_interdits | TEXT | nullable | Liste des usages interdits (ex. "Arrosage, remplissage piscines, etc.") |
| date_debut | DATETIME | NOT NULL | Début de la restriction |
| date_fin | DATETIME | nullable | Fin prévue ; NULL = durée indéterminée |
| statut | VARCHAR(20) | DEFAULT 'active', indexed | Statut : `planifiee`, `active`, `levee` |
| created_at | TIMESTAMP | | Créé à |
| updated_at | TIMESTAMP | | Modifié à |

**Index :** `(statut)`  
**Relations :** `Zone` (nombreux à un)

**Enums :**
- Types : vigilance (faible), alerte (moyen), alerte renforcée (élevé), crise (critique)
- Statuts : planifiée, active, levée

**Modèle :** `App\Models\Drought\Restriction`

---

### 2.4. Table `scheduled_cuts` — Coupures programmées

**Migration :** `2026_10_17_120014_create_scheduled_cuts_table.php`

**Champs :**
| Champ | Type | Contrainte | Description |
|-------|------|-----------|-------------|
| id | BIGINT UNSIGNED | PK | Identifiant unique |
| zone_id | BIGINT UNSIGNED | FK, nullable | Zone impactée |
| infrastructure_id | BIGINT UNSIGNED | FK, nullable | Infrastructure concernée (pompe, station, etc.) |
| motif | VARCHAR(180) | NOT NULL | Raison (ex. "Maintenance préventive réseau") |
| date_debut | DATETIME | NOT NULL | Début de la coupure |
| date_fin | DATETIME | NOT NULL | Fin prévue |
| statut | VARCHAR(20) | DEFAULT 'planifiee', indexed | Statut : `planifiee`, `en_cours`, `terminee`, `annulee` |
| created_at | TIMESTAMP | | Créé à |
| updated_at | TIMESTAMP | | Modifié à |

**Index :** `(statut)`  
**Relations :** `Zone` (nombreux à un), `Infrastructure` (nombreux à un)

**Enums :**
- Statuts : planifiée, en cours, terminée, annulée

**Modèle :** `App\Models\Drought\ScheduledCut`

---

### 2.5. Relations de données

```
Zone
├─ hasMany → WaterLevel (niveaux des ressources)
├─ hasMany → ConsumptionReading (consommations)
├─ hasMany → Restriction (restrictions d'usage)
└─ hasMany → ScheduledCut (coupures programmées)

Infrastructure
└─ hasMany → ScheduledCut (coupures programmées liées)

Drought\Restriction
└─ belongsTo Zone (zone optionnelle)

Drought\ScheduledCut
├─ belongsTo Zone
└─ belongsTo Infrastructure
```

---

## 3. Contrôleurs et leurs responsabilités

### 3.1. Contrôleurs Back-Office (Gestion authentifiée)

#### `Back\WaterLevelController`
**Responsabilités :** CRUD complet sur les mesures de niveaux  
**Routes :**
- `GET /back/water-levels` → `index()` — Liste paginée, filtrable par zone et type
- `GET /back/water-levels/create` → `create()` — Formulaire création
- `POST /back/water-levels` → `store()` — Enregistrement avec validation
- `GET /back/water-levels/{level}/edit` → `edit()` — Formulaire édition
- `PUT /back/water-levels/{level}` → `update()` — Mise à jour
- `DELETE /back/water-levels/{level}` → `destroy()` — Suppression

**Données passées à la vue :**
- zones (pour sélecteur)
- types (enum)
- level/levels (modèles)

---

#### `Back\ConsumptionReadingController`
**Responsabilités :** CRUD sur les relevés + prévisions par régression linéaire  
**Routes :**
- `GET /back/consumption-readings` → `index()` — Liste paginée, filtrable par zone
- `GET /back/consumption-readings/previsions` → `forecast()` — Vue prévisions multi-zone
- `GET /back/consumption-readings/create` → `create()` — Formulaire
- `POST /back/consumption-readings` → `store()` — Enregistrement
- `GET /back/consumption-readings/{reading}/edit` → `edit()` — Formulaire édition
- `PUT /back/consumption-readings/{reading}` → `update()` — Mise à jour
- `DELETE /back/consumption-readings/{reading}` → `destroy()` — Suppression

**Particularité :** Injection du service `DroughtAnalyzer` pour calculs prévisions

---

#### `Back\RestrictionController`
**Responsabilités :** CRUD sur les restrictions avec filtres avancés  
**Routes :**
- `GET /back/restrictions` → `index()` — Filtrable par zone, statut, type
- `GET /back/restrictions/create` → `create()` — Formulaire
- `POST /back/restrictions` → `store()` — Enregistrement
- `GET /back/restrictions/{restriction}/edit` → `edit()` — Édition
- `PUT /back/restrictions/{restriction}` → `update()` — Mise à jour
- `DELETE /back/restrictions/{restriction}` → `destroy()` — Suppression

**Filtrage :** Zone, type (vigilance/alerte/alerte renforcée/crise), statut

---

#### `Back\ScheduledCutController`
**Responsabilités :** CRUD sur les coupures programmées  
**Routes :**
- `GET /back/scheduled-cuts` → `index()` — Filtrable par zone et statut
- `GET /back/scheduled-cuts/create` → `create()` — Formulaire
- `POST /back/scheduled-cuts` → `store()` — Enregistrement
- `GET /back/scheduled-cuts/{cut}/edit` → `edit()` — Édition
- `PUT /back/scheduled-cuts/{cut}` → `update()` — Mise à jour
- `DELETE /back/scheduled-cuts/{cut}` → `destroy()` — Suppression

**Données :** zones, infrastructures (pour associations)

---

### 3.2. Contrôleur Front-Office (données publiques)

#### `Front\PublicDroughtController`
**Responsabilités :** Exposition publique des données critiques + calcul d'indice de risque  
**Routes :**
- `GET /secheresse` → `index()` — Tableau de bord public (toutes zones, risques, restrictions actives, coupures)
- `GET /secheresse/{zone}` → `zone()` — Détail d'une zone avec historique niveaux, prévisions, restrictions applicables

**Calculs effectués (via DroughtAnalyzer) :**
- **Indice de risque** (0–100) par zone basé sur :
  - Niveau critique de la source (40 pts max)
  - Tendance consommation (20 pts max)
  - Restrictions actives (25 pts max)
  - Alertes qualité actives (10 pts)
  - Incidents réseau (10 pts)
- **Prévisions** de consommation (régression linéaire simple, 3 mois)

---

### 3.3. Service `DroughtAnalyzer`

**Localisation :** `App\Services\DroughtAnalyzer`

**Responsabilités :** Analyses métier complexes (REUSE across Back et Front)

**Méthodes publiques :**

#### `forecast(Zone $zone, int $months = 3): array`
Prévision de consommation par régression linéaire sur les 12 derniers mois de données.

**Retour :**
```php
[
    'history' => [
        ['label' => 'Jan 2026', 'valeur' => 12000.50],
        ...
    ],
    'forecast' => [
        ['label' => 'Apr 2026', 'valeur' => 12500.75],
        ...
    ],
    'tendance' => 'hausse|baisse|stable',
    'message' => 'texte descriptif'
]
```

**Cas limites :**
- Moins de 2 relevés → retourne message "indisponible"
- Variation > 5% → tendance "hausse", sinon > -5% → "baisse", sinon "stable"

---

#### `riskScore(Zone $zone): array`
Calcul d'un indice composite de risque sécheresse (0–100) + facteurs contributifs.

**Paramètres examinés :**
1. Niveau dernier barrage/réservoir vs seuils critiques/alertes
2. Variation consommation (à partir forecast)
3. Nombre de restrictions actives
4. Alertes qualité actives
5. Incidents réseau ouverts (type fuite/coupure)

**Retour :**
```php
[
    'score' => 65,
    'niveau' => 'eleve|critique|modere|faible',
    'facteurs' => [
        'Niveau Barrage Mellah sous seuil critique.',
        'Consommation en hausse de 12%.',
        '2 restriction(s) active(s).',
        ...
    ]
]
```

**Niveaux :**
- 75+ : critique
- 50-74 : élevé
- 25-49 : modéré
- 0-24 : faible

---

## 4. Routes / Endpoints existants (Web + API)

### 4.1. Endpoints Web actuels

#### Routes Front-Office (publiques)
```
GET  /secheresse                          → PublicDroughtController@index()
GET  /secheresse/{zone}                   → PublicDroughtController@zone()
```

#### Routes Back-Office (authentifiées, Gestionnaire+Admin)
```
GET    /back/water-levels                 → WaterLevelController@index()
GET    /back/water-levels/create          → WaterLevelController@create()
POST   /back/water-levels                 → WaterLevelController@store()
GET    /back/water-levels/{level}/edit    → WaterLevelController@edit()
PUT    /back/water-levels/{level}         → WaterLevelController@update()
DELETE /back/water-levels/{level}         → WaterLevelController@destroy()

GET    /back/consumption-readings         → ConsumptionReadingController@index()
GET    /back/consumption-readings/previsions → ConsumptionReadingController@forecast()
GET    /back/consumption-readings/create  → ConsumptionReadingController@create()
POST   /back/consumption-readings         → ConsumptionReadingController@store()
GET    /back/consumption-readings/{reading}/edit → ConsumptionReadingController@edit()
PUT    /back/consumption-readings/{reading} → ConsumptionReadingController@update()
DELETE /back/consumption-readings/{reading} → ConsumptionReadingController@destroy()

GET    /back/restrictions                 → RestrictionController@index()
GET    /back/restrictions/create          → RestrictionController@create()
POST   /back/restrictions                 → RestrictionController@store()
GET    /back/restrictions/{restriction}/edit → RestrictionController@edit()
PUT    /back/restrictions/{restriction}   → RestrictionController@update()
DELETE /back/restrictions/{restriction}   → RestrictionController@destroy()

GET    /back/scheduled-cuts               → ScheduledCutController@index()
GET    /back/scheduled-cuts/create        → ScheduledCutController@create()
POST   /back/scheduled-cuts               → ScheduledCutController@store()
GET    /back/scheduled-cuts/{cut}/edit    → ScheduledCutController@edit()
PUT    /back/scheduled-cuts/{cut}         → ScheduledCutController@update()
DELETE /back/scheduled-cuts/{cut}         → ScheduledCutController@destroy()
```

### 4.2. API REST — INEXISTANT
**Constat :** Aucune API REST actuellement exposée pour la Gestion 4. Tous les endpoints sont web (HTML Blade).

**Lacune majeure :** Impossible d'intégrer des clients tiers (mobiles, IoT, dashboards externes) ou d'exposer les données de manière structurée (JSON).

---

## 5. Vues et interface utilisateur existantes

### 5.1. Vues Front-Office publiques

**Localisation :** `resources/views/front/drought/`

#### `front.drought.index` (/secheresse)
- Affichage d'un tableau de toutes les zones avec indice de risque en couleur
- Liste des restrictions actives (dernières 15 avec zone)
- Liste des coupures programmées/en cours (dernières 15)
- Design responsive (grid layout)

#### `front.drought.zone` (/secheresse/{zone})
- Détail d'une zone : nom, commune, population
- Graphique historique + prévisions de consommation
- Derniers niveaux de sources mesurés (histogramme ou liste)
- Restrictions applicables (zone + globales)
- Coupures prévues pour cette zone
- Lien retour vers liste complète

### 5.2. Vues Back-Office

**Localisation :** `resources/views/back/drought/`

#### Niveaux (`water/levels/`)
- `index.blade.php` : Liste paginée, filtrable (zone, type)
- `create.blade.php` : Formulaire création (zone, source, type, niveau, seuils)
- `edit.blade.php` : Formulaire édition

#### Consommation (`consumption/`)
- `index.blade.php` : Liste paginée, filtrable par zone
- `forecast.blade.php` : Vue prévisions multi-zone avec graphiques historique + forecast
- `create.blade.php` : Formulaire création (zone, période, consommation, nb abonnés)
- `edit.blade.php` : Formulaire édition

#### Restrictions (`restrictions/`)
- `index.blade.php` : Liste filtrable (zone, type, statut)
- `create.blade.php` : Formulaire création
- `edit.blade.php` : Formulaire édition

#### Coupures (`cuts/`)
- `index.blade.php` : Liste filtrable (zone, statut)
- `create.blade.php` : Formulaire création (zone, infrastructure, motif, dates)
- `edit.blade.php` : Formulaire édition

### 5.3. Composants Espace Citoyen

**Localisation :** `resources/views/front/citizen/`

#### `subscriptions.blade.php`
- Interface pour s'abonner aux alertes (zones + types : qualité, restrictions, coupures)

#### `dashboard.blade.php`
- Widget "Restrictions en vigueur" affichant les restrictions actives applicables au citoyen

---

## 6. Lacunes identifiées dans l'implémentation actuelle

### 6.1. Lacunes technologiques critiques

| # | Lacune | Impact | Sévérité |
|---|--------|--------|----------|
| 1 | **Aucune API REST publique** | Impossible pour clients tiers (mobiles, IoT, externes) d'accéder à données structurées | CRITIQUE |
| 2 | **Pas de webhooks/événements** | Pas d'alertes temps réel ; citoyen doit consulter manuellement le site | CRITIQUE |
| 3 | **Pas d'authentification API** | Futur impossible si API REST créée | HAUTE |
| 4 | **Pas de cache de données** | Requêtes DB répétées pour chaque prévision/risque | MOYENNE |
| 5 | **Pas de jobs async** | Calculs/exports longs bloquent l'interface | MOYENNE |

### 6.2. Lacunes fonctionnelles

| # | Lacune | Impact | Sévérité |
|---|--------|--------|----------|
| 6 | **Prévisions simples** | Régression linéaire basique, pas d'ML ; pas météo/saisonnalité | HAUTE |
| 7 | **Pas de rapports/exports** | Gestionnaires ne peuvent pas générer rapports Excel/PDF | HAUTE |
| 8 | **Pas d'import en masse** | Impossible charger 100+ mesures; une par une seulement | HAUTE |
| 9 | **Pas de notifications proactives** | Pas d'alertes SMS/email automatiques au dépassement seuil | HAUTE |
| 10 | **Pas de graphiques avancés** | Prévisions affichées en tableaux ; manque visu temps réel | MOYENNE |
| 11 | **Pas de comparaison inter-zones** | Impossible comparer 2 zones côte à côte | BASSE |
| 12 | **Pas d'audit** | Pas d'historique complet des modifications (qui/quand/quoi) | MOYENNE |
| 13 | **Pas de batchs/maintenance** | Migrations de données ad hoc impossibles | BASSE |

### 6.3. Lacunes sécurité / robustesse

| # | Lacune | Impact | Sévérité |
|---|--------|--------|----------|
| 14 | **Pas de rate-limiting** | API future vulnérable DoS | CRITIQUE |
| 15 | **Pas de validation côté serveur uniforme** | Risques injections/données malformées | HAUTE |
| 16 | **Pas de chiffrement données sensibles** | Données publiques en clair en DB | BASSE |
| 17 | **Pas de versioning API** | Ruptures futures compatibilité client | HAUTE |

### 6.4. Lacunes données / analyse

| # | Lacune | Impact | Sévérité |
|---|--------|--------|----------|
| 18 | **Pas de fournisseur météo** | Prévisions déconnectées météo réelle | HAUTE |
| 19 | **Pas d'intégration IoT** | Mesures manuelles seulement, pas de capteurs | HAUTE |
| 20 | **Pas de historical deepdive** | Impossible analyser tendances 2+ ans | MOYENNE |
| 21 | **Pas de simulation sécheresse** | Impossible tester scénarios "et si" | BASSE |

---

## 7. Propositions de fonctionnalités avancées

### 7.1. APIs REST avancées

#### 7.1.1 Endpoints JSON pour intégrations tierces

```
# Lecture (publique)
GET  /api/v1/drought/zones              → Liste zones + risques courants
GET  /api/v1/drought/zones/{id}         → Détail zone + historique
GET  /api/v1/drought/zones/{id}/risk    → Score de risque détaillé + facteurs
GET  /api/v1/drought/zones/{id}/levels  → Niveaux dernières 24h/7j
GET  /api/v1/drought/zones/{id}/forecast → Prévisions consommation
GET  /api/v1/drought/restrictions       → Restrictions actives + planifiées
GET  /api/v1/drought/scheduled-cuts     → Coupures programmées

# Gestion (authentifiée, Gestionnaire)
POST   /api/v1/drought/water-levels     → Créer mesure
PUT    /api/v1/drought/water-levels/{id} → Mettre à jour
DELETE /api/v1/drought/water-levels/{id} → Supprimer

POST   /api/v1/drought/consumption-readings → Créer relevé
PUT    /api/v1/drought/consumption-readings/{id} → Mettre à jour
DELETE /api/v1/drought/consumption-readings/{id} → Supprimer

POST   /api/v1/drought/restrictions     → Créer restriction
PUT    /api/v1/drought/restrictions/{id} → Mettre à jour
DELETE /api/v1/drought/restrictions/{id} → Supprimer

POST   /api/v1/drought/scheduled-cuts   → Créer coupure
PUT    /api/v1/drought/scheduled-cuts/{id} → Mettre à jour
DELETE /api/v1/drought/scheduled-cuts/{id} → Supprimer
```

**Format de réponse :** JSON standard avec pagination, metadata, erreurs structurées

**Authentification :** Bearer tokens (OAuth2 ou Laravel Sanctum)

**Rate limiting :** 1000 req/heure pour publics, 5000 pour gestionnaires

---

#### 7.1.2 Format de réponse standardisé

```json
{
  "data": {
    "id": 1,
    "zone_id": 1,
    "niveau": 45.5,
    "status": "alerte",
    ...
  },
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8,
    "from": 1,
    "to": 20
  },
  "links": {
    "first": "...",
    "prev": "...",
    "next": "...",
    "last": "..."
  },
  "errors": null
}
```

---

### 7.2. Alertes intelligentes et notifications temps réel

#### 7.2.1 Système d'abonnement par seuils

- Citoyen s'abonne à des **alertes intelligentes** non pas par zone, mais par **paramètre seuil** :
  - "M'alerter si niveau barrage Mellah < 20%"
  - "M'alerter si consommation hausse > 15% vs moyenne historique"
  - "M'alerter si restriction > alerte renforcée en cours"

#### 7.2.2 Délivrance multi-canal

- **SMS** (Twilio, AWS SNS)
- **Email** (Laravel Mail queued)
- **Push notifications** (Firebase Cloud Messaging pour app mobile)
- **WebSocket** (temps réel on-site)

#### 7.2.3 Moteur de règles

```php
// Exemple pseudo-code
$rules = [
    'critical_level_breach' => [
        'trigger' => fn($zone) => $zone->latestLevel()->niveauStatut() === 'critique',
        'channels' => ['sms', 'email', 'push'],
        'template' => 'drought.alerts.critical_level'
    ],
    'consumption_spike' => [
        'trigger' => fn($zone) => $this->consumptionVariation($zone) > 20,
        'channels' => ['email', 'push'],
    ],
];
```

---

### 7.3. Tableaux de bord analytiques avancés

#### 7.3.1 Tableau de bord gestionnaire enrichi

1. **Vue synthétique critiques :**
   - Carte des zones colorées par risque (heatmap)
   - KPIs : nb zones en crise, restrictions actives, coupures cette semaine

2. **Analyse comparative inter-zones :**
   - Graphique : consommation par zone (séries temporelles superposées)
   - Tableau : zone, population, consommation/hab, niveau, tendance

3. **Prévisions détaillées :**
   - Graphique : historique + forecast avec intervalle de confiance (IC 80%)
   - Alertes si prévision dépasse seuil critique

4. **Incidents & anomalies :**
   - Détection automatique : pic anormal de consommation
   - Lien vers incidents associés (fuites, coupures sauvages)

#### 7.3.2 Rapports téléchargeables

- **Format :** PDF, Excel, CSV
- **Contenu :**
  - Résumé exécutif (chiffres clés, recommandations)
  - Graphiques tendances et prévisions
  - Tableau détaillé mesures + restrictions
  - Métadonnées (généré par, date, signature gestionnaire)

**Exemple :** "Rapport Sécheresse Janvier 2026 — Zone Centre-Ville.pdf"

---

### 7.4. Intégrations externes (météo, IoT)

#### 7.4.1 Intégration données météo

**Provider :** OpenWeatherMap, Météo-France, NOAA

**Données récupérées :**
- Précipitations (mm/jour)
- Température (min/max)
- Humidité relative
- Débit rivières (si disponible)

**Utilisation :**
- Enrichir prévisions consommation (ajuster baisse si pluie prévue)
- Contexte pour restrictions (crise si sécheresse + canicule)
- Alertes corrélées météo

**Implémentation :** Job cron quotidien, cache 24h

---

#### 7.4.2 Intégration IoT / capteurs

**Architecture :**
- Capteurs autonomes (LoRaWAN, NB-IoT) transmettent niveaux à hub central
- Hub expose webhook ou API REST
- Aquasecure polling ou receiving webhooks toutes les 15-30min

**Données :**
- Niveaux barrage temps réel
- Débits en sortie d'infrastructures
- Consommation par district (smart meters)

**Implémentation :** Nouveau model `IoTReading`, job d'import, stockage historique

---

### 7.5. Prédictions et modèles ML avancés

#### 7.5.1 Consommation : au-delà régression linéaire

**Amélioration 1 : Saisonnalité + tendance**
- Décomposition temps : Trend + Seasonal + Residual
- Library : Statsmodels (Python) ou équivalent Laravel (php-ml)
- Captures pics été/hiver automatiquement

**Amélioration 2 : Ensemble methods**
- ARIMA + Prophet + Random Forest
- Pondérer modèles selon MAE historique
- Retourner IC (intervalles de confiance)

**Amélioration 3 : Facteurs externes**
- Température (corrélation forte)
- Jours fériés (creux généralement)
- Événements (festivals, travaux)

**Output :**
```php
[
    'forecast' => [...],
    'lower_bound' => [...],  // IC 10%
    'upper_bound' => [...],  // IC 90%
    'model_used' => 'ensemble',
    'mae' => 450.5,
    'rmse' => 650.2,
]
```

#### 7.5.2 Détection d'anomalies

- Algorithme Isolation Forest sur historique niveaux/débits
- Flagge mesures aberrantes (fuite, capteur cassé, etc.)
- Alerter gestionnaire pour validation manuelle

---

#### 7.5.3 Simulation sécheresse (Scenario Planning)

**Outil :** Tableau de simulation "Et si…"
- Input : sélectionner scénario météo (ex. "Pas de pluie 3 mois")
- Système ajuste prévisions consommation + niveaux
- Output : recommandations automatiques (restrictivité, coupures)

**Exemple :** "Si pluie = 0 mm et T = +5°C vs normale, zone Centre-Ville passe Critique J+45"

---

### 7.6. Monitoring et audit

#### 7.6.1 Audit complet

Table `drought_audit_logs` :
```
id, model_type, model_id, action, old_values, new_values, user_id, ip_address, created_at
```

Exemples :
- "ConsumptionReading#42 CREATED par Gestionnaire#5 à 10.25.192.1"
- "Restriction#7 UPDATED : statut active → levee par Gestionnaire#3"

---

#### 7.6.2 Monitoring alertes

Dashboard temps réel :
- État des webhooks (dernière exécution, erreurs)
- Queue jobs (enqueued, processing, failed)
- Santé API (latence, erreurs)
- Audit des alertes envoyées (timestamps, succès, erreurs)

---

### 7.7. Autres améliorations

#### 7.7.1 Import/Export en masse

- **CSV upload :** 1000+ mesures en une action (validées par chunk, rapport d'erreurs)
- **Export :** Excel avec graphiques intégrés, PDF rapports
- **Synchronisation :** push mesures depuis système tiers via API

#### 7.7.2 Permissions granulaires

- Gestionnaire Zone A : droit voir/éditer que mesures Zone A
- Gestionnaire National : vue tous données
- Citoyen : lecture seule, données dépublicisées

#### 7.7.3 Widgets/dashboards personnalisés

- Citoyen construit son propre dashboard (zone préférée, paramètres clés)
- Gestionnaire pins alertes critiques, masque zones non-relevantes
- Partage de vues (URL publique avec token temporaire)

#### 7.7.4 Intégration ERP/GIS

- Synchroniser zones avec cadastre (longueur réseau, population précise)
- Exporter vers GIS (QGIS, ArcGIS) pour cartographie avancée
- Lier à workorders maintenance via API (SAP, Maximo)

---

## 8. Priorités de développement recommandées

### Phase 1 : Fondations (T1 2026) — Critique

**Objectif :** Rendre Gestion 4 intégrable et robuste

1. **API REST v1 (Sanctum + Controllers API)**
   - [ ] Architecture API Resources (fractals)
   - [ ] Endpoints lecture publique + CRUD gestionnaire
   - [ ] Rate limiting, versionning, error handling
   - [ ] Tests unitaires + intégration
   - **Effort :** 3-4 sprints
   - **ROI :** Ouverture à clients tiers, intégration futur

2. **Système d'abonnement aux alertes seuils**
   - [ ] Nouvelle table `alert_rules` (trigger, seuil, canaux)
   - [ ] Queue jobs SMS/Email
   - [ ] Tâche CRON validation des règles
   - **Effort :** 2 sprints
   - **ROI :** Citoyen alerté proactivement, réduit charge support

3. **Import/Export en masse**
   - [ ] CSV upload validé par chunk (Laravel Queue)
   - [ ] Export Excel/PDF avec graphiques
   - [ ] Rapport d'erreurs d'import détaillé
   - **Effort :** 1-2 sprints
   - **ROI :** Economie temps saisie, sortie reporting

### Phase 2 : Intelligence (T2-T3 2026) — Haute

**Objectif :** Prévisions fiables, détection anomalies

4. **Prévisions avancées (ML saisonnalité)**
   - [ ] Intégration Prophet ou ARIMA via Python worker
   - [ ] Décomposition time-series, IC 80%
   - [ ] Facteurs externes (météo, fériés)
   - **Effort :** 3-4 sprints
   - **ROI :** Accuracy +30%, recommandations automatiques

5. **Intégration météo OpenWeatherMap**
   - [ ] Webhooks réception données
   - [ ] Enrichissement prévisions
   - [ ] Alertes canicule+sécheresse corrélées
   - **Effort :** 1 sprint
   - **ROI :** Contexte réel, prévisions améliorées

6. **Détection anomalies (Isolation Forest)**
   - [ ] Training modèle sur historique
   - [ ] Scoring mesures nouvelles
   - [ ] UI flagging manuel
   - **Effort :** 1-2 sprints
   - **ROI :** Détection fuites/capteurs cassés automatique

### Phase 3 : Expérience (T3-T4 2026) — Moyenne

**Objectif :** Tableaux de bord riches, reporting

7. **Dashboard gestionnaire enrichi**
   - [ ] Heatmap zones (risque)
   - [ ] Graphiques comparatifs inter-zones
   - [ ] KPIs synthèse
   - [ ] Intégration carte (Leaflet/Mapbox)
   - **Effort :** 2-3 sprints
   - **ROI :** Visibilité stratégique, décisions rapides

8. **Rapports PDF/Excel automatisés**
   - [ ] Templating (résumé, graphiques, recommandations)
   - [ ] Signature gestionnaire
   - [ ] Archivage + audit
   - **Effort :** 1-2 sprints
   - **ROI :** Traçabilité, partage décideurs

### Phase 4 : Avancé (T4 2026+) — Opportunités

9. **Intégration IoT / capteurs temps réel**
10. **Simulation scénarios sécheresse**
11. **Permissions granulaires par zone**
12. **Webhooks sortants (flux PUSH clients tiers)**
13. **App mobile natif (React Native / Flutter)**

---

## Résumé des composants clés identifiés

### Modèles
- `WaterLevel` : mesures niveaux sources
- `ConsumptionReading` : relevés consommation mensuels
- `Restriction` : restrictions d'usage (4 types, 3 statuts)
- `ScheduledCut` : coupures programmées
- `Zone` : divisions géographiques

### Contrôleurs (Back & Front)
- `Back\WaterLevelController` : CRUD niveaux
- `Back\ConsumptionReadingController` : CRUD consommation + prévisions
- `Back\RestrictionController` : CRUD restrictions
- `Back\ScheduledCutController` : CRUD coupures
- `Front\PublicDroughtController` : exposition publique + indice de risque
- `Services\DroughtAnalyzer` : moteur calculs (forecast, risk score)

### Routes
- Web uniquement (pas API REST) ; 7 endpoints front + ~28 back CRUD

### Données générées
- ~18 relevés consommation par zone (test)
- ~3-4 restrictions par zone (test)
- ~3 coupures (test)
- Niveaux quotidiens (test)

### Lacunes majeures
1. Pas d'API REST
2. Pas d'alertes temps réel/webhooks
3. Prévisions trop simples (régression linéaire)
4. Pas d'intégration météo/IoT
5. Pas d'import en masse

---

**Fin du rapport — Analyse complète de la Gestion 4 : Sécheresse, Consommation et Ressources**
