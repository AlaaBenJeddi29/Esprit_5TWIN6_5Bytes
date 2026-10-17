# Modules 2 / 3 / 4 — État d'implémentation

**Statut : IMPLÉMENTÉS** (migrations additives + modèles + CRUD Back Office + Front Office
+ tests). Ce document remplace l'ancienne liste d'attente de spécifications.

## Module 2 — Incidents

- Tables : `incidents`, `incident_photos`, `incident_comments`, `incident_status_histories`.
- Types : fuite, rupture, contamination, coupure, pression, panne, autre.
- Statuts : `nouveau` → `en_cours` / `en_attente` → `résolu` → `clos` (transitions
  contrôlées, historique horodaté).
- Rattachements : zone et infrastructure optionnelles, duplicate éventuel (`duplicate_of_id`).
- Workflow citoyen : création + classification heuristique locale, photos, commentaires,
  suivi par référence aléatoire. Les commentaires `is_internal` ne sont jamais exposés
  au citoyen ni au suivi public.
- Back Office : CRUD, affectation à un gestionnaire/admin, changement de statut,
  commentaires internes, téléchargement/suppression des photos.

## Module 3 — Qualité de l'eau

- Tables : `quality_thresholds`, `water_quality_samples`, `water_quality_parameters`,
  `water_quality_alerts`.
- Seuils configurables par zone et paramètre (pH, turbidité, chlore, nitrates…), avec
  bornes min/max cohérentes.
- Un prélèvement porte une référence unique et plusieurs paramètres mesurés.
- L'analyse (`QualityAnalyzer`) marque la conformité et génère automatiquement une alerte
  active en cas de dépassement ; la résolution d'alerte est possible en Back Office.
- Front Office : consultation publique de la qualité par zone.

## Module 4 — Sécheresse

- Tables : `water_levels`, `consumption_readings`, `restrictions`, `scheduled_cuts`,
  `alert_subscriptions`.
- Niveaux des ressources par zone avec seuils de vigilance/alerte/crise.
- Consommations uniques par zone et période ; prévisions calculées localement.
- Restrictions et coupures programmées avec validation des dates et statuts.
- Abonnements citoyens par zone et type d'alerte (unicité utilisateur/zone).
- Front Office : situation sécheresse par zone, restrictions et coupures à venir.

## Vérification

Les trois modules sont couverts par les tests de `tests/Feature/Back`
(`IncidentModuleTest`, `QualityModuleTest`, `DroughtModuleTest`) et
`tests/Feature/Front/PublicAndCitizenTest`, ainsi que par `DemoDataSeeder`.
