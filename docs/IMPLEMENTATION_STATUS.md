# AquaSecure — État d'implémentation

Dernière mise à jour : 2026-10-17 · Workspace : `Aquasecure/`

## Lots livrés

| Lot | Portée | Statut |
|-----|--------|--------|
| 0 | Fondations partagées (thème Back Office par rôle, composants Blade, doc C1–C5) | ✅ |
| 1 | Auth & rôles (`UserRole`, `users.role`/`is_active`, dispatch `/dashboard`, middleware `role`, redirection post-login sûre) | ✅ |
| 1b | CRUD gestionnaires (admin uniquement) + activation/désactivation | ✅ |
| G1 | Infrastructures (`zones`, `infrastructures`, `maintenances`) | ✅ |
| G2 | Incidents (`incidents`, photos, commentaires internes, historique de statuts) | ✅ |
| G3 | Qualité de l'eau (`quality_thresholds`, prélèvements, paramètres, alertes) | ✅ |
| G4 | Sécheresse (`water_levels`, `consumption_readings`, `restrictions`, `scheduled_cuts`, abonnements) | ✅ |
| G5 | Projets & financement (`contractors`, `projects`, phases, financements, documents) | ✅ |

Front Office : accueil, qualité, sécheresse, infrastructures, projets, suivi public de
signalement, espace citoyen (signalements, alertes, dons simulés). Back Office :
CRUD opérationnels pour les cinq gestions, commentaires internes, gestion des comptes
gestionnaires réservée aux administrateurs.

## Schéma (migrations additives — 28 fichiers)

- Socle : `users`, `cache`, `jobs`, `reports`, rôles/activation.
- G1/G5 : `zones`, `infrastructures`, `maintenances`, `contractors`, `projects`,
  `project_phases`, `fundings`, `project_documents`.
- G2 : `incidents`, `incident_photos`, `incident_comments`, `incident_status_histories`.
- G3 : `quality_thresholds`, `water_quality_samples`, `water_quality_parameters`,
  `water_quality_alerts`.
- G4 : `alert_subscriptions`, `water_levels`, `consumption_readings`, `restrictions`,
  `scheduled_cuts`.
- Complément G5 : `add_est_public_to_project_documents`.

Les 14 migrations `2026_10_17_120001` → `120014` sont additives, ordonnées pour les FK,
avec rollback symétrique. Aucune migration destructive (`migrate:fresh` / `db:wipe`).

## Base de données et vérifications

Environnement : **MySQL 8 via XAMPP exclusivement** — aucun support SQLite.

- Développement : base `aquasecure` (`.env` inchangé), 14 migrations déjà appliquées.
- Tests automatisés : base dédiée `aquasecure_test`, forcée par `phpunit.xml`
  (`DB_CONNECTION=mysql`, `DB_DATABASE=aquasecure_test`, `DB_URL` vide). Le garde de
  `tests/TestCase.php` refuse toute autre configuration et toute config mise en cache.
  La base doit être créée manuellement (voir README) ; `RefreshDatabase` la réinitialise.
- La suite Feature MySQL n'a pas été exécutée dans cet environnement (base de test non
  confirmée / migrations non autorisées) ; seuls les contrôles sans connexion MySQL l'ont été.

| Contrôle | Commande | Résultat |
|----------|----------|----------|
| Tests Unit (sans base) | `php artisan test tests/Unit` | 1 passed (1 assertion) |
| Suite Feature MySQL | `php artisan test` | **Non exécutée** — à lancer après création de `aquasecure_test` |
| Compilation vues | `php artisan view:cache` | OK |
| Build front | `npm run build` | OK (61 modules, vite 7.3.7) |
| Validation Composer | `composer validate --no-check-publish` | OK |

## Points de sécurité validés (couverts par les tests)

- Citoyen → 403 sur tout le Back Office ; gestionnaire → 403 sur `/back/gestionnaires`.
- Rôle lu côté serveur ; l'inscription force `citoyen` ; aucun rôle privilégié créable
  hors espace administrateur.
- Un utilisateur désactivé ne peut pas se connecter.
- Signalements citoyens : accès limité au propriétaire ; commentaires internes filtrés
  côté serveur ; suivi public par référence aléatoire sans données personnelles.
- Documents projet : téléversement contrôlé (types/MIME, 10 Mo), stockage privé sur le
  disque `local`, téléchargement public uniquement si `est_public`, suppression du
  fichier avec l'enregistrement ou le projet.
- `donateur_id` accepté uniquement pour `source = don` et uniquement pour un citoyen.
- `responsable_id` d'un projet limité aux gestionnaires/administrateurs.
- Dons Front Office simulés : aucun paiement réel, aucun fournisseur d'IA externe.

## Vérifications NON réalisées (MySQL non exécuté)

Conformément aux consignes, aucune migration ni aucun seeder n'a été exécuté sur la base
MySQL locale. Restent à valider par le propriétaire : exécution réelle des migrations,
contraintes FK/InnoDB et rendu des enum accentués sur MySQL 8.

**À exécuter par le propriétaire** (voir l'ordre exact dans le rapport d'audit) :

```bash
php artisan migrate
php artisan db:seed --class=DemoUsersSeeder
php artisan db:seed --class=DemoDataSeeder
```

Les seeders de démonstration sont explicites, idempotents, refusés en production et
dépendent de variables `AQUASECURE_DEMO_*` / `AQUASECURE_ADMIN_*` définies localement.

## Limites connues

1. Vérification MySQL réelle non effectuée dans cet environnement.
2. L'assistant projet est déterministe et local (pas de fournisseur d'IA externe).
3. Les exigences Git du barème ne sont pas revendiquées.
