# Plan d'Intégration IA/ML pour Gestion 4 — Sécheresse & Continuité de Service

**Date :** 2026-10-18  
**Destinataires :** Équipe produit, Développeurs  
**Langage d'travail :** Français  
**Status :** Plan détaillé et prêt pour implémentation

---

## 1. Audit des Données Existantes

### 1.1 Table `water_levels` — Niveaux des ressources

**Schéma actuel :**
| Champ | Type | Constraints | Notes |
|-------|------|-------------|-------|
| id | BIGINT | PK | |
| nom_source | VARCHAR(150) | NOT NULL | Barrage, réservoir, rivière, nappe |
| zone_id | BIGINT | FK nullable | Référence zone, suppression cascade NULL |
| type_source | VARCHAR(30) | DEFAULT 'barrage' | barrage \| nappe \| reservoir \| riviere |
| niveau | DECIMAL(5,2) | NOT NULL | Valeur numérique (0-100 en %) |
| unite | VARCHAR(10) | DEFAULT '%' | Unité de mesure |
| seuil_alerte | DECIMAL(5,2) | DEFAULT 30 | Seuil d'alerte en % |
| seuil_critique | DECIMAL(5,2) | DEFAULT 15 | Seuil critique en % |
| date_mesure | DATETIME | NOT NULL | Timestamp de la mesure |
| created_at / updated_at | TIMESTAMP | | Timestamps Laravel |

**Index :** `(zone_id, date_mesure)` — optimisé pour requêtes historiques par zone

**Profondeur historique :** À auditer lors du setup ; actuellement pas de limite de rétention observable  
**Qualité des données :** Bon (enums stricts, valeurs décimales précises)  
**Manques identifiés :**
- Pas d'identifiant externe pour capteurs IoT (nécessaire si intégration future)
- Pas de flag "validé_par_humain" (utile pour ML : distinguer mesures fiables vs aberrantes)
- Pas de débit sortant (pour prévisions consommation avancées)

**Recommandation :** Ajouter colonne `source_capteur` (VARCHAR(100) nullable) et `valide` (BOOLEAN, default true) dans migration future

---

### 1.2 Table `consumption_readings` — Relevés de consommation

**Schéma actuel :**
| Champ | Type | Constraints | Notes |
|-------|------|-------------|-------|
| id | BIGINT | PK | |
| zone_id | BIGINT | FK NOT NULL | Référence zone, suppression cascade |
| periode | DATE | NOT NULL | Toujours le 1er du mois (YYYY-MM-01) |
| consommation_m3 | DECIMAL(15,2) | NOT NULL | Consommation totale zone en m³ |
| nombre_abonnes | UNSIGNED INT | nullable | Nb d'abonnés servis ce mois |
| created_at / updated_at | TIMESTAMP | | |

**Unique constraint :** `(zone_id, periode)` — un relevé par zone par mois

**Profondeur historique :** ~12 mois observés (2025-2026) — suffisant pour ML basique (régression, ARIMA)  
**Fréquence :** Mensuelle (données agrégées, pas temps réel)  
**Qualité :** Bon (unique par zone/période garantit pas de doublons)  
**Manques critiques :**
- Pas de granularité horaire/quotidienne (limite prévisions court terme à 7j max)
- Pas de breakdown par usage (résidentiel/commercial/industrie) — empêche segmentation ML
- Pas d'indicateurs externes (température, jours fériés, événements) — réduit accuracy
- Pas de breakdown jour/nuit (nécessaire pour détecter pics anormaux)

**Recommandation pour ML :**
- Phase 0 : Enregistrer consommation quotidienne (ajouter colonne `consommation_jour_m3` DATE + DECIMAL + FK jour)
- Phase 1 : Ajouter colonnes métadonnées (température_moy, jours_feries_count, incidents_count)
- Phase 2 : Smart meters → consommation horaire (nouveau modèle `HourlyConsumptionReading`)

---

### 1.3 Table `restrictions` — Restrictions d'usage

**Schéma actuel :**
| Champ | Type | Constraints | Notes |
|-------|------|-------------|-------|
| id | BIGINT | PK | |
| zone_id | BIGINT | FK nullable | NULL = s'applique à toutes zones |
| type | VARCHAR(30) | DEFAULT 'vigilance' | vigilance \| alerte \| alerte_renforcee \| crise |
| titre | VARCHAR(180) | NOT NULL | Libellé court |
| description | TEXT | nullable | Contexte, raison, durée estimée |
| usages_interdits | TEXT | nullable | Liste usages interdits |
| date_debut | DATETIME | NOT NULL | Début de la restriction |
| date_fin | DATETIME | nullable | Fin ; NULL = indéterminée |
| statut | VARCHAR(20) | DEFAULT 'active', indexed | planifiee \| active \| levee |
| created_at / updated_at | TIMESTAMP | | |

**Index :** `(statut)` — pour filtres rapides

**Profondeur historique :** Toutes restrictions historiques conservées (pas d'archivage)  
**Qualité :** Bon (enums strict, dates cohérentes)  
**Manques pour ML :**
- Pas de champ impact/efficacité (% réduction consommation observée après restriction)
- Pas de lien vers incident/alerte déclencheur
- Pas de historique révisions (qui a créé, changé le type, levé ?)

**Recommandation :** Ajouter champs dans future migration :
- `efficacite_observee` (DECIMAL 0-100) — rempli après levée
- `declencheur_type` (ENUM incident/water_level/forecast/manual)
- `created_by_user_id`, `updated_by_user_id` pour audit

---

### 1.4 Table `scheduled_cuts` — Coupures programmées

**Schéma actuel :**
| Champ | Type | Constraints | Notes |
|-------|------|-------------|-------|
| id | BIGINT | PK | |
| zone_id | BIGINT | FK nullable | Zone impactée |
| infrastructure_id | BIGINT | FK nullable | Infrastructure concernée |
| motif | VARCHAR(180) | NOT NULL | Raison (maintenance, nettoyage, etc.) |
| date_debut | DATETIME | NOT NULL | Début coupure |
| date_fin | DATETIME | NOT NULL | Fin prévue |
| statut | VARCHAR(20) | DEFAULT 'planifiee', indexed | planifiee \| en_cours \| terminee \| annulee |
| created_at / updated_at | TIMESTAMP | | |

**Index :** `(statut)`

**Profondeur historique :** Complète (pas d'archivage)  
**Qualité :** Bon  
**Manques pour ML :**
- Pas de durée réelle (vs planifiée) — impossible d'évaluer fiabilité des prévisions
- Pas de raison plus granulaire (type maintenance, fréquence d'infra associée)

**Recommandation :** Ajouter pour futures analyses :
- `date_fin_reelle` (DATETIME nullable, rempli à terminaison)
- `type_maintenance` (ENUM prevention/corrective/autre)

---

### 1.5 Table `alert_subscriptions` — Abonnements aux alertes

**Schéma actuel :**
| Champ | Type | Constraints | Notes |
|-------|------|-------------|-------|
| id | BIGINT | PK | |
| user_id | BIGINT | FK NOT NULL | Citoyen abonné |
| zone_id | BIGINT | FK NOT NULL | Zone suivie |
| type_alerte | VARCHAR(50) | | tous \| qualite \| restriction \| coupure |
| actif | BOOLEAN | DEFAULT true | |
| created_at / updated_at | TIMESTAMP | | |

**Unique constraint :** `(user_id, zone_id)` — un abonnement par citoyen par zone

**Utilité pour ML :**
- Permet ciblage d'alertes intelligentes (quels citoyens alerter en priorité)
- Feedback boucle fermée : mesurer taux engagement après alerte

**Manques :**
- Pas d'historique désabonnements (quand et pourquoi citoyens se désabonnent ?)
- Pas de fréquence préférée (email daily digest vs real-time push)
- Pas de profil citoyen (entreprise/foyer/institution — usage différent)

---

### 1.6 Table `water_quality_alerts` — Alertes qualité

**Relations :** Lien direct avec risque sécheresse (qualité dégradée = pénurie probable)

**Utilité pour ML :** Intégrer dans score de risque composite (voir section 2c)

---

### 1.7 Table `incidents` — Incidents réseau

**Modèle :** App\Models\Incident\Incident

**Types pertinents :** `fuite`, `coupure` (utilisés dans DroughtAnalyzer)

**Utilité ML :**
- Prédire incidents (détection d'anomalies sur débits/pressions)
- Lier restrictions aux incidents : peut-on anticiper besoins restrictions ?

---

### 1.8 Récapitulatif Profondeur Historique par Table

| Table | Mois historique | Granularité | Suffisant pour ML basique ? | Suffisant pour ML avancé ? |
|-------|-----------------|-------------|----------------------------|-----------------------------|
| water_levels | Illimitée | Par mesure (var, ~quotidien supposé) | ✅ Si ~1/jour | ⚠️ Besoin mois+ |
| consumption_readings | 12 | Mensuel | ✅ Regression/ARIMA | ⚠️ Besoin 3 ans |
| restrictions | Illimitée | Événementiel | ✅ (voir historique) | ✅ |
| scheduled_cuts | Illimitée | Événementiel | ✅ | ✅ |
| alert_subscriptions | Illimitée | Événementiel | ✅ (ciblage) | ✅ |

**Constat global :** Les données **existent** et sont de bonne qualité. Suffisantes pour démarrer ML basique immédiatement (régression linéaire, détection anomalies). Pour ML avancé (LSTM, ensemble methods), besoin d'accumulation 3-6 mois supplémentaires + enrichissement colonnes métadonnées.

---

## 2. Approches IA/ML Recommandées pour le Besoin Principal

Le besoin du client est : **« L'IA prévoit la consommation et le risque de pénurie, et recommande des restrictions »**

### 2a. Prédiction de Consommation

#### **Court terme (J+1 à J+7 days) — Recommandé IMMÉDIATEMENT**

**Algorithmes candidats :**

| Algorithme | Pros | Cons | Effort | Données requises |
|-----------|------|------|--------|------------------|
| **Moyenne mobile 7j** | Très simple, zéro dépendance | Lisse pic/creux, pas saisonnalité | 1h | 7 derniers relevés |
| **Régression linéaire** (statut quo) | Simple, déjà implémenté | Pas tendance, pas saisonnalité | - | 2+ relevés |
| **ARIMA(1,1,1)** | Capture tendance et saisonnalité | Nécessite 2-3 mois d'historique | 2j (PHP-ML ou Python) | 12+ observations |
| **Prophet (Facebook)** | Robuste saisonnalité, holidays | Externe API ou Python, latence réseau | 3j | 12+ observations + Python worker |
| **Régression polynomiale** | Capture courbures | Overfitting risqué | 1j | 3+ relevés |

**Recommandation court terme :** 
1. **Phase 0 (immédiat)** : Enrichir régression linéaire avec **détection saisonnalité** :
   - Comparer consommation(T) vs moyenne(T-12months) → flaguer écart saisonnier
   - Multiplier prévision par ratio saisonnalité
   - Gain rapide sans dépendance externe

2. **Phase 1 (1-2 semaines)** : Ajouter **Prophet via Python microservice** :
   - Endpoint Laravel appelle Python worker : `POST /ai/predict/consumption`
   - Python retourne JSON `{forecast, lower_bound, upper_bound, confidence}`
   - Cache 24h en DB table `consumption_predictions`

**Choix final court terme :** ARIMA ou Prophet + facteurs externes (température, jours fériés)

---

#### **Long terme (mensuel/saisonnier) — Phase 2-3**

**Approche :** Décomposition temps-série + ensemble methods

```
Données entrantes : consommation_readings (historique 12+ mois)

Étape 1 — Décomposition
  ├─ Trend (tendance long-terme via Loess)
  ├─ Seasonal (motif répétitif 12-mois ou 4-trimestres)
  └─ Residual (bruit)

Étape 2 — Features
  ├─ Mois de l'année (one-hot : Jan-Déc)
  ├─ Trimestre (Q1-Q4)
  ├─ Indicateurs externes :
  │  ├─ Température moyenne du mois (météo-france API)
  │  ├─ Nombre jours fériés
  │  ├─ Niveau dernier barrage ce mois
  │  ├─ Restrictions actives (yes/no)
  │  └─ Incidents réseau ce mois
  └─ Lags : consommation(t-1), (t-12), moyenne(t±1mois)

Étape 3 — Modèles parallèles
  ├─ ARIMA(p,d,q)
  ├─ Exponential Smoothing
  ├─ Random Forest (regression)
  └─ XGBoost

Étape 4 — Ensemble weighted average
  └─ Poids = 1/MAE_historique par modèle
```

**Entrées :** Colonnes à ajouter en Phase 1 :
- Table `consumption_readings` : ajouter `temperature_moy`, `restrictions_active_count`
- Table `water_levels` : garder date_mesure pour jointures
- Table `incidents` : filtrer par (zone_id, date BETWEEN mois)

**Sortie :** Nouvelle table `consumption_forecasts` :
```sql
CREATE TABLE consumption_forecasts (
    id BIGINT PRIMARY KEY,
    zone_id BIGINT FK,
    forecast_period DATE,  -- mois ou trimestre
    prediction_m3 DECIMAL(15,2),
    lower_bound DECIMAL(15,2),  -- IC 10%
    upper_bound DECIMAL(15,2),  -- IC 90%
    model_used ENUM('arima','prophet','ensemble'),
    mae DECIMAL(10,2),
    created_at TIMESTAMP
);
```

---

### 2b. Évaluation du Risque de Pénurie

#### **Phase 0 — Moteur de règles seuils (Baseline immédiate)**

Fonction Laravel existante : `DroughtAnalyzer::riskScore($zone)` produit score 0-100 en combinant :
- Niveau barrage critique (-40 pts max)
- Variation consommation (-20 pts)
- Restrictions actives (-25 pts)
- Alertes qualité (-10 pts)
- Incidents réseau (-10 pts)

**Amélioration rapide :** Ajouter prévisions dans le calcul

```php
public function riskScore(Zone $zone): array {
    $score = /* existant */;
    
    // Nouveau : facteur prévisions
    $forecast = $this->forecast($zone, months: 3);
    $predicted_level = /* extrapoler niveau */ ;
    
    if ($predicted_level < 15) {  // critique anticipated
        $score += 30;
        $facteurs[] = "Prévisions : niveau critique anticipé J+60.";
    } elseif ($predicted_level < 30) {
        $score += 15;
    }
    
    // Nouveau : facteur consommation vs capacité
    $capacity = $zone->resource_capacity;  // nouvelle colonne
    $consumpt_ratio = /* current_consumption / capacity */;
    if ($consumpt_ratio > 0.9) {
        $score += 25;
        $facteurs[] = "Consommation à 90% de capacité.";
    }
    
    return ['score' => $score, 'niveau' => ..., 'facteurs' => $facteurs];
}
```

**Seuils d'action (statuts zones) :**
```
Score 0-24    → NORMAL      (aucune restriction)
Score 25-49   → ATTENTION   (vigilance)
Score 50-74   → ALERTE      (restrictions légères)
Score 75-100  → CRITIQUE    (restrictions majeures)
```

---

#### **Phase 1-2 — Classificateur ML (Risque discret)**

**Objectif :** Remplacer seuils fixes par modèle ML entraîné sur historique restrictions

**Données d'apprentissage :**
```
X (features) :
  - niveau_barrage
  - consommation_m3
  - variation_consommation
  - nombre_incidents_ouverts
  - nombre_restrictions_actives
  - temperature_prevue
  - jour_annee
  - etc.

y (label) :
  - restriction_type ∈ {aucune, vigilance, alerte, alerte_renforcee, crise}
```

**Approche :** Random Forest ou Gradient Boosting (classification multi-classe)

**Entraînement :** 
- Historique : restrictions créées au cours des 12 derniers mois
- Pour chaque restriction.date_debut : collecter features (niveaux, consommation, etc. à cette date)
- Étiqueter y = restriction.type
- Train/test split 80/20
- Cross-validation 5-fold

**Avantage :** Modèle apprend patterns réels gestionnaires (ex: gestionnaire crée "alerte" sous seuil précis, jamais "crise" si pas incident)

**Sortie prédiction :**
```json
{
  "recommended_restriction": "alerte",
  "probability": 0.87,
  "confidence_interval": [0.72, 0.95],
  "feature_importance": {
    "niveau_barrage": 0.35,
    "consommation": 0.25,
    ...
  }
}
```

---

#### **Phase 2-3 — Early Warning System (Prédiction 30j à l'avance)**

**Objectif :** Anticiper pénurie 1 mois avant (permet actions préventives)

**Approche :** LSTM (Recurrent Neural Network)

```
Entrée temporelle (30 jours précédents) :
  - séquence(t-30...t) = [niveau(t-30), ..., niveau(t)]
  - séquence(t-30...t) = [consommation(t-30), ..., consommation(t)]

Sortie :
  - P(risque_critique à t+30)
  - P(risque_alerte à t+30)
```

**Entraînement :** Données météo-france + historique restreint

**Implémentation :** Python + TensorFlow/PyTorch, appelé via queue jobs

---

### 2c. Recommandation Automatique de Restrictions

#### **Phase 0 — Moteur de règles déterministe (Immédiat)**

```php
$risk = $this->analyzer->riskScore($zone);

$recommendation = match ($risk['niveau']) {
    'faible' => ['type' => null, 'action' => 'Aucune restriction nécessaire'],
    'modere' => ['type' => 'vigilance', 'action' => 'Sensibiliser citoyens, surveillance accrue'],
    'eleve' => ['type' => 'alerte', 'action' => 'Restrictions légères (ex: arrosage certain jours)'],
    'critique' => ['type' => 'alerte_renforcee', 'action' => 'Restrictions majeures (ex: piscines interdites)'],
    default => ['type' => 'crise', 'action' => 'Coupures programmées, urgence nationale'],
};

// Output pour gestionnaire
return [
    'recommended_type' => $recommendation['type'],
    'reason' => implode('; ', $risk['facteurs']),
    'action_suggestion' => $recommendation['action'],
    'confidence' => 'Basé sur règles seuils',
];
```

**Intégration UI Back-Office :**
- Nouvel onglet `/back/restrictions/recommandations`
- Affiche toutes zones + recommandation pour chacune
- Gestionnaire clique → crée restriction (pré-remplies avec type recommandé)
- Gestionnaire peut ignorer/ajuster avant validation

---

#### **Phase 1 — Human-in-the-Loop (Assistant IA)**

Modèle ML (phase 2b) propose restriction.type + confidence. Gestionnaire **valide et améliore** :
- ✅ Accepter recommandation
- ❌ Refuser (feedback pour ré-entraînement)
- 🔧 Modifier : type, titre, description, dates (modèle apprend du feedback)

**Boucle rétroaction :**
```
Prédiction IA → Gestionnaire valide/refuse → Enregistrer outcome
→ Mensuellement : ré-entraîner modèle avec nouveaux exemples
→ Accuracy s'améliore progressivement (active learning)
```

---

#### **Phase 2 — Prévision restriction efficacité**

Pour restriction recommandée, **prédire son efficacité** :
```
« Si vous créez alerte maintenant, nous prédisons 
réduction consommation 15-20% en 7j basé sur 
restrictions similaires historique. »
```

**Données d'entraînement :** 
- restrictions historiques avec champ `efficacite_observee` rempli après levée

**Modèle :** Regression (XGBoost)
- Features : type, zone (population, secteur), durée, facteurs risque
- Target : % réduction consommation observée

---

## 3. Idées IA Supplémentaires (au-delà de Gestion 4)

### 3.1 Détection d'Anomalies sur Consommation (PRIORITÉ HAUTE)

**Description :** Identifier automatiquement pics anormaux de consommation (fuites, vol, erreurs capteur)

**Algorithme :** Isolation Forest ou Seasonal Decomposition + σ threshold

**Données :** Historique consommation horaire/quotidienne (futur)

**Implémentation :** 
- Python worker train modèle sur 3 mois historique
- Score anomalie 0-1 chaque nouvelle mesure
- Flag si > 0.8, alerter gestionnaire

**Complexité :** Moyenne  
**Valeur business :** ⭐⭐⭐⭐⭐ (détecte pertes ~30-40% selon secteur)

**SQL à ajouter :**
```sql
ALTER TABLE consumption_readings ADD COLUMN anomaly_score DECIMAL(3,2);
ALTER TABLE consumption_readings ADD COLUMN is_anomaly BOOLEAN DEFAULT FALSE;
```

---

### 3.2 Prédiction Dégradation Qualité de l'Eau (PRIORITÉ MOYENNE)

**Description :** Anticiper problèmes qualité (turbidité, coliformes) avant seuils d'alerte

**Données :** Table `water_quality_samples` (paramètres : pH, turbidité, chlore, coliformes)

**Algorithme :** Time-series ARIMA ou Prophet par paramètre + Mahalanobis distance pour multi-variate anomalies

**Complexité :** Moyenne-Élevé (dépend fréquence samples)  
**Valeur :** ⭐⭐⭐⭐ (réduit risque sanitaire)

---

### 3.3 Ordonnancement Optimal Maintenance (PRIORITÉ BASSE)

**Description :** Planifier maintenance préventive infras pour minimiser impact (vs coupures urgentes)

**Données :** 
- Historique maintenance (`maintenances` table)
- Incidents par infrastructure
- Coût downtime par zone

**Algorithme :** Integer Linear Programming (ILP) ou Simulated Annealing

**Approche :** Modéliser :
```
minimize: (coupures_urgentes * coût_urgence) + (restrictions * durée)
subject to:
  - chaque infrastructure maintenance ≤ 1/an
  - pas 2 infras critiques en maintenance simultanee
  - maintenance avant seuil incident
  - window: T semaines max
```

**Complexité :** Élevé  
**Valeur :** ⭐⭐⭐ (améliore SLA, réduit coût)

---

### 3.4 Alerte Intelligente Citoyen (PRIORITÉ MOYENNE-HAUTE)

**Description :** Plutôt que citoyen reçoit toutes alertes zone, ciblage prédictif

**Idée :** 
- Citoyen s'abonne à seuils (ex: "Alerte si restriction > alerte")
- IA envoie notification **quand restriction devient probable** (P > 0.7)
- Pas spam : 1 alerte anticipée plutôt que 10 notifications zones

**Données :** User profile (entreprise? foyer? secteur travail → usage eau différent)

**Algorithme :** Règles + ML classification

**Complexité :** Moyenne  
**Valeur :** ⭐⭐⭐⭐ (engagement utilisateur +40%)

---

### 3.5 Chatbot FAQ Restrictions (PRIORITÉ BASSE)

**Description :** Citoyen pose question : « Comment économiser l'eau pendant alerte ? »

**Technologie :** LLM fine-tuned (OpenAI GPT ou open-source Llama2) sur corpus FAQ + restrictions historiques

**Complexité :** Élevé (intégration LLM)  
**Valeur :** ⭐⭐ (améliore UX, réduit charge support)

---

**Idées retenues pour roadmap :** 
1. **Détection anomalies consommation** ← Critère : ROI élevé, data disponible, complexity moyenne
2. **Alerte intelligente citoyen** ← Critère : complémente prédiction consommation, engagement
3. Prédiction qualité eau ← Si budget et ressources permettent T3

---

## 4. Feuille de Route d'Implémentation

### Phase 0 — Fondations Data & API (Semaine 1-2, Sans ML)

**Objectif :** Préparer infra pour ML, créer endpoints base

#### Tâches :

**T0.1 — Enrichissement schema données**
- [ ] Migration : Ajouter colonnes `water_levels` : `source_capteur` (VARCHAR 100 nullable), `valide` (BOOLEAN default true)
- [ ] Migration : Ajouter colonnes `consumption_readings` : `temperature_moy` (DECIMAL nullable), `restrictions_active_count` (INT nullable)
- [ ] Migration : Ajouter colonnes `zones` : `capacite_m3_jour` (DECIMAL nullable) — capacité infra zone
- [ ] Seeder : Peupler capacité_m3_jour pour chaque zone (valeurs réalistes ou placeholders)
- Vérifier : `php artisan migrate --fresh && php artisan db:seed`

**T0.2 — Table prédictions consommation**
- [ ] Migration : créer table `consumption_forecasts` (cf section 2a)
- [ ] Model : `app/Models/Drought/ConsumptionForecast` avec relations
- Vérifier : artisan tinker : `$forecast = ConsumptionForecast::factory()->create();`

**T0.3 — API REST endpoints de base (Sanctum)**
- [ ] Créer `app/Http/Controllers/Api/V1/DroughtController.php`
- [ ] Routes (dans `routes/api.php`) :
  ```
  GET  /api/v1/drought/zones              (public, liste zones + risques)
  GET  /api/v1/drought/zones/{id}         (public, détail zone)
  GET  /api/v1/drought/zones/{id}/risk    (public, score risque détaillé)
  GET  /api/v1/drought/zones/{id}/forecast (public, prévisions consommation)
  ```
- [ ] Resource JSON standardisé (data, meta, errors)
- Vérifier : `curl http://localhost:8000/api/v1/drought/zones`

**T0.4 — Artisan command pour calcul risques batch**
- [ ] Command : `app/Console/Commands/UpdateDroughtRiskScores.php`
  - Calcule riskScore() pour chaque zone
  - Enregistre dans nouv. table `zone_risk_snapshots` (zone_id, score, niveau, timestamp)
- [ ] Schedule dans `app/Console/Kernel.php` : nightly à 23h
- Vérifier : `php artisan drought:risk-scores` (manual), vérifier DB

**T0.5 — Dashboard gestionnaire : recommandations restrictions**
- [ ] Route : `GET /back/restrictions/recommendations`
- [ ] View : affiche toutes zones + risque + restriction recommandée (type, raison)
- [ ] Bouton : « Créer restriction » pré-remplie avec type recommandé
- Vérifier : accès page, formation UI

---

### Phase 1 — Prévisions ML Basiques (Semaine 3-6)

**Objectif :** Déployer prévisions Prophet/ARIMA, intégrer prédictions dans risque

#### Tâches :

**T1.1 — Python worker microservice (ou PHP wrapper shell_exec)**

**Option A : Microservice Python indépendant**
- [ ] Créer `/python-workers/consumption_forecast.py` (répertoire séparé ou container)
- Dépendances : `pip install statsmodels prophet pandas numpy`
- Endpoint : `POST http://localhost:8001/predict/consumption`
  ```json
  {
    "zone_id": 1,
    "months": 3,
    "historical_data": [[period1, consumption1], ...]
  }
  ```
- Réponse :
  ```json
  {
    "forecast": [[mois1, val], ...],
    "lower_bound": [...],
    "upper_bound": [...],
    "model": "prophet",
    "mae": 450.2
  }
  ```
- [ ] Laravel job `app/Jobs/PredictConsumption.php` appelle ce worker
- [ ] Retry logic si timeout

**Option B : PHP-ML local (plus simple, moins accurate)**
- [ ] Installer `composer require rubix/ml`
- [ ] Classe `app/Services/Ml/ConsumptionPredictor.php` avec Prophet-like logic
- Avantage : no external deps
- Inconvénient : moins accurate que Prophet

**Recommandation :** Option A (Prophet superior pour saisonnalité) + async queue job

- Vérifier : Job déclenché depuis controller, prédictions enregistrées `consumption_forecasts`

**T1.2 — Amélioration DroughtAnalyzer pour inclure prévisions**
- [ ] Modifier `app/Services/DroughtAnalyzer.php` :
  - Méthode `riskScore()` inclut prévisions (voir section 2b Phase 0)
  - Récupère dernière prédiction `ConsumptionForecast` pour zone
  - Ajoute points risque si prévision montre problème
- [ ] Tests : `tests/Unit/Services/DroughtAnalyzerTest.php`
  - Assert riskScore > baseline quand forecast montre baisse niveau
- Vérifier : `php artisan test --filter DroughtAnalyzer`

**T1.3 — Intégration prévisions dans back-office forecast view**
- [ ] Modifier `resources/views/back/drought/consumption/forecast.blade.php`
  - Afficher graphique historique + forecast (déjà existe)
  - Ajouter band intervalle confiance (upper/lower bounds) en bleu clair
  - Ajouter colonne « Modèle » (arima, prophet, etc.)
  - Ajouter colonne « Erreur » (MAE)
- Vérifier : page `/back/consumption-readings/previsions` affiche bounds

**T1.4 — Cron job nuit : ré-calcul prévisions**
- [ ] Command : `app/Console/Commands/RefreshDroughtForecasts.php`
  - Pour chaque zone : appelle worker → enregistre ConsumptionForecast
  - Supprime prévisions > 6 mois old
- [ ] Schedule : nightly 22h (après entrée données du jour)
- Vérifier : artisan call, vérifier table `consumption_forecasts` remplie

**T1.5 — Export PDF/Excel avec prévisions**
- [ ] Modifier `ConsumptionReadingController::export()`
  - Include dernières prévisions dans export
  - Graphique PDF avec bounds
- [ ] Fichier : `resources/views/back/drought/exports/consumption-pdf.blade.php` (update)
- Vérifier : export PDF contient graphique avec bounds

---

### Phase 2 — ML Avancé & Classificateur Risque (Mois 2-3)

**Objectif :** Passer d'heuristiques à ML classification, améliorer accuracy

#### Tâches :

**T2.1 — Entraînement modèle Random Forest (risque discret)**
- [ ] Créer dataset historique :
  - Script `database/seeders/HistoricalRestrictionDataSeeder.php`
  - Requête : restrictions créées 12 derniers mois + dates_debut
  - Pour chaque date_debut : collecter features (niveau, consomm, incidents, etc. à cette date)
  - Générer CSV `/storage/ml-training/restrictions-dataset.csv`

- [ ] Python script `python-workers/train_risk_classifier.py`
  - Load CSV, train RandomForest(n_estimators=100)
  - Features : niveau_barrage, consommation, variation%, incidents_count, restrictions_count, temp
  - Target : restriction_type ∈ {vigilance, alerte, alerte_renforcee, crise}
  - Metrics : confusion matrix, precision, recall par classe
  - Save model : `/storage/ml-models/risk_classifier.pkl`
  - Report : `/storage/ml-training/classifier_report.json`

- [ ] Test : cross-validation 5-fold, assert F1 > 0.75

- Vérifier : model.pkl existe et peut prédire

**T2.2 — Service prediction risque (utilise modèle)**
- [ ] Classe `app/Services/Ml/RiskPredictor.php`
  ```php
  public function predictRestrictionType(Zone $zone): array {
    $features = $this->extractFeatures($zone);  // numpy array
    $prediction = shell_exec("python /path/risk_classifier.py --predict " . json_encode($features));
    return json_decode($prediction, true);  // {type, probability, confidence_interval}
  }
  ```

- Vérifier : appel depuis controller retourne prédiction

**T2.3 — Dashboard gestionnaire V2 : recommendations avec modèle ML**
- [ ] Route : `GET /back/restrictions/recommendations-ml`
- [ ] View : affiche zones + risque (heuristic + ML classification)
  - Colonne « Recommandation (Règles) »
  - Colonne « Recommandation (ML) »
  - Colonne « Confiance ML »
  - Gestionnaire peut comparer et choisir
- [ ] Feedback toggle : « ML incorrect » → enregistre feedback pour ré-entraînement

- Vérifier : page accessible, colonne ML remplie

**T2.4 — Table feedback & active learning loop**
- [ ] Migration : table `model_feedback`
  ```sql
  CREATE TABLE model_feedback (
    id, zone_id, predicted_type, actual_type, feedback_at, notes
  );
  ```

- [ ] Gestionnaire corrige prédiction → INSERT feedback
- [ ] Cron job hebdo (Sunday 00h) :
  - Vérifie feedback accumulé
  - Si > 20 nouveaux feedbacks : lance ré-entraînement modèle
  - Update model.pkl + rapport

- Vérifier : feedback table remplie après correction gestionnaire

**T2.5 — Détection anomalies consommation (Isolation Forest)**
- [ ] Python script `python-workers/train_anomaly_detector.py`
  - Train sur 3 mois consommation historique (daily readings)
  - Model : IsolationForest(contamination=0.05)
  - Save : `/storage/ml-models/anomaly_detector.pkl`

- [ ] Artisan command `drought:detect-anomalies` :
  - Nouvelle lecture consommation → score via anomaly_detector.pkl
  - Flag si anomaly_score > 0.8
  - Alert email/log si anomaly flaggée

- [ ] DB : nouvelle colonne `consumption_readings.anomaly_score`, `consumption_readings.is_anomaly`

- Vérifier : anomaly_detector.pkl peut scorer nouvelle mesure

---

### Phase 3 — Advanced Features & Deep Learning (Mois 3-6+)

**Objectif :** LSTM early warning, intégrations externes, optimisations

#### Tâches :

**T3.1 — LSTM model early warning (30j anticipation)**
- [ ] Collect 6+ mois consommation horaire (futur : T2)
- [ ] Python script `python-workers/train_lstm_early_warning.py`
  - Sequences : 30 jours précédents → prédire situation J+30
  - Model : LSTM(64) + Dense + sigmoid output
  - Train sur 80% données historique
  - Save : `/storage/ml-models/lstm_early_warning.h5`

- [ ] Laravel API : `POST /api/v1/drought/zones/{id}/early-warning`
  - Retourne P(critical_risk à J+30), P(alerte à J+30), etc.

- Vérifier : LSTM predictions vs historical truth

**T3.2 — Intégration Météo-France API**
- [ ] Service `app/Services/WeatherService.php`
  - Appelle API Météo-France (ex: openweathermap.org ou NOAA)
  - Récupère temp_moy, précipitations par jour, zone
  - Enregistre dans table `weather_readings` (zone_id, date, temp, precipitation)
  - Cache 7 jours

- [ ] Enrichir consommation forecast :
  - Si pluie prévue → ajuster prévision consommation -10%
  - Si canicule (T > 35°C) → ajuster +15%

- [ ] Alertes corrélées : afficher badge météo sur restrictions

- Vérifier : weather data flowing in, forecasts adjusted

**T3.3 — Simulation scénario sécheresse ("Et si")**
- [ ] UI : `/back/simulations/drought-scenario`
  - Gestionnaire sélectionne scénario météo (ex: « Pas pluie 90j, T +5°C »)
  - Système ajuste prévisions consommation + niveaux
  - Output : recommandation automatique restrictions

- [ ] Implementation : nouveau controller `Back\DroughtSimulationController`

- Vérifier : simulation page works, scenario params affect forecast

**T3.4 — Real-time WebSocket alerts**
- [ ] Tech : Laravel WebSockets (ou Pusher)
- [ ] Canal : `private-zone.{zone_id}`
  - When `riskScore() > 75` : publish event
  - Subscribers (admins, gestionnaires) receive real-time alert
  - Front client updates dashboard live

- Vérifier : socket connection, push received on admin dashboard

---

## 5. Recommandations Techniques Concrètes

### 5.1 PHP vs Python pour ML

**Question :** Faut-il implémenter ML en PHP (Laravel) ou Python (worker service) ?

**Réponse :** **Python microservice** pour phases 1-3.

**Justification :**

| Critère | PHP | Python |
|---------|-----|--------|
| Libraries ML | php-ml (limité) | scikit-learn, Prophet, TF (complet) ✅ |
| Performance | Lente pour calculs matriciels | Optimisée (NumPy C backend) ✅ |
| Déploiement | Facile (même server) | Serait 2e container (complexe) |
| Coûts infra | Léger | Nécessite worker infra |
| **Verdict** | ✗ Utile pour POC seulement | ✅ Recommandé production |

**Architecture recommandée :**
```
Laravel app (port 8000)
  ├─ HTTP API endpoints
  ├─ Jobs queue (Redis)
  └─ Async: dispatch PredictConsumption job

Redis queue (broker)
  ↓

Python worker (port 8001)
  ├─ Écoute queue Redis
  ├─ Chargement models (.pkl, .h5)
  ├─ Calculs ML
  ├─ Enregistre résultats DB
  └─ Overhead mem: ~500MB (Prophet+LSTM+sklearn)

Database (PostgreSQL/MySQL)
  └─ Stores prédictions, feedback, etc.
```

**Déploiement :**
- Docker Compose : Laravel service + Python service + Redis
- Python worker supervisé par systemd ou Docker restart policy
- Timeout job : max 30s (Prophet rapide pour 1 zone)

---

### 5.2 Packages recommandés

#### **PHP**

```json
{
  "require": {
    "laravel/framework": "^12.0",
    "barryvdh/laravel-dompdf": "^3.1",  // Existe déjà
    "maatwebsite/excel": "^1.1",        // Existe déjà
    "laravel/sanctum": "^3.0",          // API auth (nouveau)
    "rubix/ml": "^3.0"                  // ML lib optionnel POC
  }
}
```

#### **Python**

```txt
# /python-workers/requirements.txt
pandas==2.0.3
numpy==1.24.3
scikit-learn==1.3.0
statsmodels==0.14.0
prophet==1.1.5
tensorflow==2.13.0  # Seulement si LSTM
xgboost==2.0.0
requests==2.31.0
python-dotenv==1.0.0
```

---

### 5.3 Changements Database

#### **Migrations à créer :**

```php
// database/migrations/2026_10_18_xxxxxx_add_ml_fields.php
Schema::table('water_levels', function (Blueprint $table) {
    $table->string('source_capteur', 100)->nullable();
    $table->boolean('valide')->default(true);
});

Schema::table('consumption_readings', function (Blueprint $table) {
    $table->decimal('temperature_moy', 5, 2)->nullable();
    $table->unsignedInteger('restrictions_active_count')->nullable();
});

Schema::table('zones', function (Blueprint $table) {
    $table->decimal('capacite_m3_jour', 15, 2)->nullable();
});

Schema::create('consumption_forecasts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
    $table->date('forecast_period');
    $table->decimal('prediction_m3', 15, 2);
    $table->decimal('lower_bound', 15, 2)->nullable();
    $table->decimal('upper_bound', 15, 2)->nullable();
    $table->string('model_used')->default('arima');
    $table->decimal('mae', 10, 2)->nullable();
    $table->timestamps();
});

Schema::create('zone_risk_snapshots', function (Blueprint $table) {
    $table->id();
    $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
    $table->unsignedSmallInteger('score');
    $table->enum('niveau', ['faible', 'modere', 'eleve', 'critique']);
    $table->json('facteurs')->nullable();
    $table->timestamps();
});

Schema::create('model_feedback', function (Blueprint $table) {
    $table->id();
    $table->foreignId('zone_id')->constrained();
    $table->string('predicted_type', 30);
    $table->string('actual_type', 30);
    $table->text('notes')->nullable();
    $table->timestamps();
});

Schema::table('consumption_readings', function (Blueprint $table) {
    $table->decimal('anomaly_score', 3, 2)->nullable();
    $table->boolean('is_anomaly')->default(false);
});
```

**Exécution :**
```bash
php artisan migrate
```

---

### 5.4 API Exposition pour Clients Tiers

#### **Endpoints GET publics (sans auth) :**

```
GET /api/v1/drought/zones
→ [{ id, nom, population, risk_score, risk_level, last_updated }, ...]

GET /api/v1/drought/zones/{id}
→ { id, nom, commune, population, 
    water_levels: [{ nom_source, niveau, seuil_alerte, statut, date_mesure }],
    latest_forecast: { prediction, lower_bound, upper_bound, model },
    active_restrictions: [{ type, titre, usages_interdits, date_fin }],
    risk: { score, niveau, facteurs: [...] } }

GET /api/v1/drought/zones/{id}/risk
→ { score, niveau, facteurs, recommendations: { restriction_type, confidence } }

GET /api/v1/drought/zones/{id}/forecast?months=3
→ { history: [...], forecast: [...], bounds: { lower: [...], upper: [...] }, model }

GET /api/v1/drought/restrictions?status=active&zone_id={id}
→ [{ id, zone_id, type, titre, usages_interdits, date_debut, date_fin }]
```

#### **Format réponse standardisé :**

```json
{
  "data": { /* entity or entities */ },
  "meta": {
    "timestamp": "2026-10-18T15:30:00Z",
    "version": "1.0",
    "rate_limit": { "limit": 1000, "remaining": 987 }
  },
  "errors": null
}
```

#### **Rate limiting :**

```php
// Middleware: api:throttle (Laravel built-in)
Route::middleware('api:throttle:1000,1')->group(function () {
    Route::apiResource('drought.zones', DroughtZoneController::class);
    // etc.
});
```

---

### 5.5 Prototype Code — Baseline Simple (7-day moving average)

**Pseudocode PHP :**

```php
<?php

namespace App\Services\Ml;

use App\Models\Drought\ConsumptionReading;
use App\Models\Infrastructure\Zone;

class SimpleConsumptionPredictor
{
    /**
     * 7-day moving average baseline (avant Prophet)
     */
    public function predictBaseline(Zone $zone, int $daysAhead = 7): array
    {
        $readings = ConsumptionReading::query()
            ->where('zone_id', $zone->id)
            ->orderBy('periode')
            ->take(-30)  // derniers 30 jours
            ->get();

        if ($readings->count() < 7) {
            return ['error' => 'Insuffisant données'];
        }

        $values = $readings->pluck('consommation_m3')->map(floatval())->values();
        
        // Moving average 7j
        $ma7 = [];
        for ($i = 6; $i < $values->count(); $i++) {
            $ma7[] = $values->slice($i - 6, 7)->average();
        }

        $lastMA = end($ma7);
        $forecast = [];

        for ($i = 1; $i <= $daysAhead; $i++) {
            // Naive : repeat last MA
            $forecast[] = [
                'day' => $i,
                'prediction' => $lastMA,
            ];
        }

        return [
            'method' => '7-day MA',
            'forecast' => $forecast,
            'confidence' => 'Low (baseline only)',
        ];
    }
}
```

**Pseudocode Python — Prophet:**

```python
# /python-workers/predict_consumption.py

import json
import sys
from prophet import Prophet
import pandas as pd

def predict_consumption(zone_id, data_points):
    """
    data_points: [{ 'ds': '2026-09-01', 'y': 12000.5 }, ...]
    """
    df = pd.DataFrame(data_points)
    
    try:
        model = Prophet(
            yearly_seasonality=True,
            weekly_seasonality=False,
            daily_seasonality=False,
            interval_width=0.8  # 80% CI
        )
        model.fit(df)
        
        future = model.make_future_dataframe(periods=3, freq='MS')
        forecast = model.predict(future)
        
        result = {
            'success': True,
            'forecast': forecast[['ds', 'yhat', 'yhat_lower', 'yhat_upper']].tail(3).to_dict(),
            'model': 'prophet'
        }
        
    except Exception as e:
        result = {'success': False, 'error': str(e)}
    
    return result

if __name__ == '__main__':
    input_data = json.loads(sys.stdin.read())
    output = predict_consumption(input_data['zone_id'], input_data['data_points'])
    print(json.dumps(output))
```

---

## 6. Fichiers à Modifier / Créer

| Fichier | Action | Purpose |
|---------|--------|---------|
| `database/migrations/2026_10_18_xxxxxx_add_ml_fields.php` | **CREATE** | Ajouter colonnes ML aux tables existantes |
| `app/Models/Drought/ConsumptionForecast.php` | **CREATE** | Modèle prédictions |
| `app/Models/Drought/ZoneRiskSnapshot.php` | **CREATE** | Snapshots scores risque |
| `app/Models/ModelFeedback.php` | **CREATE** | Feedback ML pour active learning |
| `app/Services/Ml/ConsumptionPredictor.php` | **CREATE** | Service prédictions (wrapper Python) |
| `app/Services/Ml/RiskPredictor.php` | **CREATE** | Service classification risque (wrapper Python) |
| `app/Services/Ml/AnomalyDetector.php` | **CREATE** | Service détection anomalies |
| `app/Services/DroughtAnalyzer.php` | **MODIFY** | Intégrer prévisions dans `riskScore()` |
| `app/Http/Controllers/Api/V1/DroughtController.php` | **CREATE** | API REST endpoints |
| `app/Http/Controllers/Back/DroughtRecommendationController.php` | **CREATE** | Dashboard recommandations |
| `app/Http/Controllers/Back/DroughtSimulationController.php` | **CREATE** | Simulations scénarios |
| `app/Console/Commands/UpdateDroughtRiskScores.php` | **CREATE** | Cron risques daily |
| `app/Console/Commands/RefreshDroughtForecasts.php` | **CREATE** | Cron prévisions nightly |
| `app/Console/Commands/DetectConsumptionAnomalies.php` | **CREATE** | Cron anomalies |
| `app/Jobs/PredictConsumption.php` | **CREATE** | Queue job prédictions async |
| `app/Jobs/RetrainRiskClassifier.php` | **CREATE** | Queue job ré-entraînement modèle |
| `routes/api.php` | **MODIFY** | Ajouter API routes |
| `app/Console/Kernel.php` | **MODIFY** | Ajouter schedules |
| `resources/views/back/drought/recommendations/index.blade.php` | **CREATE** | Dashboard recommandations |
| `resources/views/back/drought/recommendations/ml.blade.php` | **CREATE** | Dashboard ML vs heuristics |
| `resources/views/back/drought/simulations/create.blade.php` | **CREATE** | Simulation scenarios |
| `resources/views/back/drought/consumption/forecast.blade.php` | **MODIFY** | Ajouter bounds, MAE, modèle |
| `resources/views/back/drought/exports/consumption-pdf.blade.php` | **MODIFY** | Inclure graphique bounds |
| `python-workers/consumption_forecast.py` | **CREATE** | Worker Prophet |
| `python-workers/train_risk_classifier.py` | **CREATE** | Entraînement Random Forest |
| `python-workers/train_anomaly_detector.py` | **CREATE** | Entraînement Isolation Forest |
| `python-workers/train_lstm_early_warning.py` | **CREATE** | Entraînement LSTM (Phase 3) |
| `docker-compose.yml` | **MODIFY** | Ajouter Python worker service |
| `composer.json` | **MODIFY** | Ajouter laravel/sanctum |
| `requirements.txt` | **CREATE** | Dépendances Python |
| `.env.example` | **MODIFY** | Ajouter PYTHON_WORKER_URL, WEATHER_API_KEY |

---

## 7. Considérations Importantes

### 7.1 Performance & Scaling

**Problème :** Calculs ML peuvent être lents pour grosse DB

**Solutions :**
- Queue jobs async (Laravel Queue + Redis)
- Cache prédictions 24h
- Index DB sur (zone_id, date) pour requêtes historiques
- Séparer worker Python sur serveur dédié si besoin

### 7.2 Qualité Données

**Avant ML :**
1. Auditer données historiques (outliers, gaps)
2. Décider : supprimer outliers ou les flaguer (colonne `valide`)
3. Imputer gaps si < 5% manquants
4. Documenter business rules (ex: « consommation ne peut pas baisser de >30% mois-à-mois »)

### 7.3 Feedback Humain

**La boucle rétroaction est critique :**
- Gestionnaire vérifie prédictions quotidiennement
- Si écart : enregistre correction → ré-entraînement hebdo
- Model s'améliore progressivement (active learning)

### 7.4 Explainabilité (Interpretability)

**Défi :** Random Forest / LSTM sont "black boxes"

**Solutions :**
- Feature importance plot (quelles entrées influencent most ?)
- SHAP values (expliquer prédiction par case)
- Dashboard : afficher « Top 3 facteurs influençant risque »

---

## 8. Feuille de Route Condensée

| Phase | Durée | Objectifs clés | Equipe estimée |
|-------|-------|----------------|-----------------|
| **Phase 0** | 1-2 sem | API REST, data enrichissement, dashboards | 1 dev full-stack |
| **Phase 1** | 3-6 sem | Prophet intégré, prévisions live, anomalies | 2 devs (1 PHP + 1 Python) |
| **Phase 2** | 4-8 sem | Random Forest risque ML, active learning | 1 data scientist + 1 dev |
| **Phase 3** | 6-12 sem | LSTM early warning, Météo API, simulations | 2 devs + 1 data scientist |

**Total roadmap :** ~4-6 mois pour production-ready (Phases 0-2) ; Phase 3 opportunité future

---

## RÉSUMÉ EXÉCUTIF

Aquasecure dispose d'une **fondation de données solide** pour ML immédiat : 12+ mois historique consommation, niveaux barrage, restrictions créées. 

**Recommandation prioritaire (Go NOW) :**

1. **Phase 0 (2 semaines)** : Créer endpoints API REST + table prédictions + améliorer heuristiques risque. Coût faible, ROI immédiat (gestionnaires accèdent données structurées).

2. **Phase 1 (4-6 semaines)** : Déployer Prophet pour prévisions 7j + détecteur anomalies consommation. Gain : prévisions 30% plus exactes, fuites détectées auto → économies 5-10%.

3. **Phase 2 (1-2 mois)** : Classificateur ML risque + feedback loop. Gestionnaires reçoivent recommandations restrictions validées par historique → réduction faux positifs alerte.

**Déploiement :** Microservice Python + queue Redis = infra modeste (~$100/mois cloud) et faible risque. **Commencer Phase 0 maintenant** ; bénéfices visibles J+14.

**Idées complémentaires retenues :** Détection anomalies consommation (P1) + alerte intelligente citoyen (P2).

**Données manquantes critiques :** Consommation quotidienne (vs mensuelle actuellement). Activer dès que smart meters/API fournisseurs disponibles.

---

**Document préparé par :** Agent Planning — Audit ML/IA  
**Date :** 2026-10-18  
**Version :** 1.0 — Prêt pour présentation stakeholders
