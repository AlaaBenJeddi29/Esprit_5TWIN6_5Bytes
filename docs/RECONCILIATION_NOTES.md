# Notes de réconciliation — Écarts documentation ↔ migrations (C1–C5)

Ce document consigne les contradictions détectées entre la documentation du dépôt
de référence et les migrations finalement exécutées. **Aucune définition métier n'a
été modifiée silencieusement.** Les migrations finales exécutées (et les modèles
correspondants) font foi pour les entités déjà implémentées.

## C1 — `projects.type` : deux jeux de valeurs d'enum

- `2026_10_05_181518_create_projects_table` crée :
  `['rénovation', 'décontamination', 'extension', 'modernisation']`
- `2026_10_06_005602_update_projects_type_enum` **remplace** ces valeurs par :
  `['réparation', 'modernisation', 'extension', 'construction']`

**Décision retenue :** l'état final est `['réparation', 'modernisation', 'extension', 'construction']`.
Dans ce workspace (base neuve), la table `projects` est créée directement avec ces valeurs
finales ; la migration de transition n'est donc pas dupliquée (aucune donnée historique à migrer).

**Documentation incohérente :** `docs/MODELS_STRUCTURE.md` mentionne l'ancien jeu ;
`RAPPORT_EVALUATION_GESTION5.md` mentionne un jeu encore différent
(`réparation, modernisation, extension, construction` vs `rénovation, décontamination…`).

## C2 — Table `fundings` : colonnes documentées mais absentes en base

`docs/DATABASE_SCHEMA_REFERENCE.md` décrit pour `fundings` :

- `source` enum `['subvention_publique', 'don', 'emprunt', 'fonds_propres']`
- une colonne `date_obtention`
- une colonne `statut` (`en_attente`, `approuve`, `rejete`)
- une colonne `description`

La migration finale exécutée `2026_10_05_181520_create_fundings_table` contient :

- `source` enum `['municipal', 'régional', 'fédéral', 'européen', 'privé', 'don']`
- `date_versement` (et **non** `date_obtention`)
- **aucune** colonne `statut`
- **aucune** colonne `description`

**Décision retenue :** la migration fait foi → `source` = `municipal/régional/fédéral/européen/privé/don`,
colonne `date_versement`, pas de `statut` ni `description`.

## C3 — Table `project_phases` : colonnes documentées mais absentes

La documentation évoque parfois `statut` et `description` sur `project_phases`.
La migration finale `2026_10_05_181519_create_project_phases_table` ne contient
**ni** `statut` **ni** `description`.

**Décision retenue :** `project_phases` = `project_id`, `contractor_id`, `nom`,
`date_debut`, `date_fin`, `cout`, `avancement`. Aucun ajout.

## C4 — Table `project_documents` : noms de colonnes divergents

`docs/DATABASE_SCHEMA_REFERENCE.md` décrit `nom` (`string 200`) et
`type_document` (enum `cahier_charges/plan_technique/rapport_etude/photo/autre`).

La migration finale exécutée `2026_10_05_181521_create_project_documents_table` contient :

- `titre` (`string 200`) — **et non** `nom`
- `type` enum `['rapport', 'photo', 'facture', 'contrat', 'plan', 'autre']` — **et non** `type_document`
- **aucune** colonne `description`

**Décision retenue :** la migration fait foi → `titre`, `type` (jeu de valeurs ci-dessus).

## C5 — Module « Projets & financement » : le Front Office de référence est incohérent

`routes/front/project.php` + `app/Http/Controllers/Front/Project/FundingController.php`
du dépôt de référence implémentent un parcours de **don citoyen** qui écrit dans `fundings` :

- `statut` = `en_attente` (colonne inexistante — cf. C2)
- `date_obtention` = `now()` (colonne inexistante — la migration n'a que `date_versement`)
- `description` = … (colonne inexistante)

De même, `ProjectController@show` (front) charge les documents avec
`where('type', 'public')`, alors que le jeu d'enum de `type` est
`['rapport', 'photo', 'facture', 'contrat', 'plan', 'autre']` (cf. C4) — `public` n'existe pas.

**Décision retenue :** le Back Office « Projets & financement » est implémenté à partir du
schéma confirmé (migrations finales). Le don citoyen est implémenté comme **don simulé**
(`source = don`, `donateur_id` citoyen, `date_versement`, aucun paiement réel) sans recourir
aux colonnes inexistantes `statut`/`date_obtention`/`description`.

La visibilité des documents publics utilise la colonne additive `est_public` (migration
`2026_10_17_120005`) au lieu du type inexistant `public`. Les documents sont téléversés sur
le disque privé `local`, puis téléchargés via le contrôleur si `est_public = true`.

**Fonctionnalités additionnelles du dépôt de référence non implémentées (workflows non confirmés) :**
archivage/restauration de projet, duplication, export PDF/Excel, approbation/rejet d'un
financement. Upload, téléchargement et suppression de fichier de document sont implémentés.
Le Back Office couvre le CRUD complet des entités confirmées du schéma.

---

## Principe

Pour toute entité déjà implémentée dans le dépôt de référence, les migrations finales
exécutées constituent la source de vérité. Les écarts ci-dessus sont documentés et non
réconciliés d'office : toute modification de définition métier nécessite une décision explicite.
