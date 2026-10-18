# Plan d'implémentation — Export PDF et Excel (Gestion 4)

## Décisions architecturales

1. **Dépendances** : Utiliser `barryvdh/laravel-dompdf ^1.0` pour les PDF (populaire, bien maintenu) et `maatwebsite/excel ^3.1` pour Excel (basé sur PhpSpreadsheet, standard dans Laravel).

2. **Service centralisé** : `DroughtExportService` dans `app/Services/Drought/` regroupe la logique de requête filtrée pour toutes les 4 entités, pour éviter la duplication. Les contrôleurs appellent ce service avec les filtres extraits de `Request`.

3. **Classes Export séparées** : Une classe `FromCollection` pour chaque entité (WaterLevelExport, ConsumptionReadingExport, etc.) pour que la mise en forme Excel soit isolée et testable.

4. **Routes d'export** : Un endpoint `GET /back/{resource}/export` accepte les query parameters `format=pdf|excel` et transmet les filtres appliqués (zone_id, type_source, statut) au service.

5. **Vues PDF** : 4 templates Blade séparés dans `resources/views/back/drought/exports/` pour chaque entité, paysage, avec en-tête (titre, date, gestionnaire), tableau avec bordures et couleurs, pied de page avec numéro.

---

## Plan détaillé

### FEAT-001 : Dépendances et Service d'export

- [ ] 1. Ajouter les dépendances dans composer.json
      Ajouter `barryvdh/laravel-dompdf` ^1.0 et `maatwebsite/excel` ^3.1 à `require`.
      Files: composer.json
      Verify: `composer show barryvdh/laravel-dompdf maatwebsite/excel` — les deux packages sont listés.

- [ ] 2. Installer les dépendances
      Exécuter `composer install` pour télécharger les packages.
      Files: composer.json, composer.lock, vendor/
      Verify: `composer install` retourne succès (exit code 0) ; vendor/barryvdh et vendor/maatwebsite existent.

- [ ] 3. Publier la config de DomPDF
      Exécuter `php artisan vendor:publish --provider=Barryvdh\\DomPDF\\ServiceProvider`.
      Files: config/dompdf.php
      Verify: `ls config/dompdf.php` — le fichier existe.

- [ ] 4. Créer DroughtExportService avec méthodes statiques pour 4 entités
      Créer `app/Services/Drought/DroughtExportService.php` avec méthodes statiques :
      - `getWaterLevelsForExport(?int $zoneId, ?string $type): Collection` — retourne WaterLevel avec zone eager-loaded, filtrés par zone_id et type_source
      - `getConsumptionReadingsForExport(?int $zoneId): Collection` — retourne ConsumptionReading avec zone, filtrés par zone_id
      - `getRestrictionsForExport(?int $zoneId, ?string $statut, ?string $type): Collection` — retourne Restriction avec zone, filtrés par zone_id, statut, type
      - `getScheduledCutsForExport(?int $zoneId, ?string $statut): Collection` — retourne ScheduledCut avec zone et infrastructure, filtrés par zone_id et statut
      
      Chaque méthode suit le pattern du contrôleur (when() conditionnel), charge les relations avec with(), tri par date décroissante.
      Files: app/Services/Drought/DroughtExportService.php
      Verify: `php artisan tinker && \\App\\Services\\Drought\\DroughtExportService::getWaterLevelsForExport(null, null)->count()` — retourne un nombre (0 ou plus).

- [ ] 5. Créer classes Export Excel pour WaterLevel
      Créer `app/Exports/Drought/WaterLevelExport.php` implémentant `Maatwebsite\\Excel\\Concerns\\FromCollection` et `WithHeadings`, `WithStyles`.
      Colonnes : [Source, Type, Zone, Niveau (%), Seuil alerte (%), Seuil critique (%), Date mesure]
      Formatage : en-têtes gras et fond bleu clair, colonnes largeur auto, bordures sur toutes les cellules, alignement : texte à gauche, nombres à droite.
      Files: app/Exports/Drought/WaterLevelExport.php
      Verify: `ls app/Exports/Drought/WaterLevelExport.php` existe ; `php artisan tinker && new \\App\\Exports\\Drought\\WaterLevelExport(collect([]))` — pas d'erreur.

- [ ] 6. Créer classe Export Excel pour ConsumptionReading
      Créer `app/Exports/Drought/ConsumptionReadingExport.php` avec colonnes [Zone, Période, Consommation (m³), Abonnés, m³/Abonné].
      Même formatage que WaterLevel ; nombres avec séparateurs de milliers.
      Files: app/Exports/Drought/ConsumptionReadingExport.php
      Verify: Fichier créé et sans erreur de syntax.

- [ ] 7. Créer classe Export Excel pour Restriction
      Créer `app/Exports/Drought/RestrictionExport.php` avec colonnes [Titre, Niveau, Zone, Date début, Date fin, Statut, Usages interdits].
      Dates formatées en dd/mm/yyyy hh:mm.
      Files: app/Exports/Drought/RestrictionExport.php
      Verify: Fichier créé.

- [ ] 8. Créer classe Export Excel pour ScheduledCut
      Créer `app/Exports/Drought/ScheduledCutExport.php` avec colonnes [Motif, Zone, Infrastructure, Date début, Date fin, Statut].
      Files: app/Exports/Drought/ScheduledCutExport.php
      Verify: Fichier créé.

---

### FEAT-002 : Routes, contrôleurs et UI

- [ ] 9. Ajouter 4 routes d'export dans web.php
      Ajouter dans le groupe `back` (middleware auth, verified, role:gestionnaire,admin) :
      - `GET /back/water-levels/export` → `WaterLevelController@export` name 'back.water-levels.export'
      - `GET /back/consumption-readings/export` → `ConsumptionReadingController@export` name 'back.consumption-readings.export'
      - `GET /back/restrictions/export` → `RestrictionController@export` name 'back.restrictions.export'
      - `GET /back/scheduled-cuts/export` → `ScheduledCutController@export` name 'back.scheduled-cuts.export'
      
      Files: routes/web.php
      Verify: `php artisan route:list | grep export` — 4 routes GET listées.

- [ ] 10. Ajouter méthode export() à WaterLevelController
      Ajouter méthode `public function export(Request $request): Response` :
      - Extraire $zoneId = $request->integer('zone_id'), $type = $request->string('type_source')
      - Appeler $levels = DroughtExportService::getWaterLevelsForExport($zoneId, $type)
      - Si $request->string('format') === 'pdf' : rendre vue 'back.drought.exports.water-levels-pdf' avec $levels, utiliser DomPDF pour générer PDF, retourner download response
      - Sinon (format=excel) : return (new WaterLevelExport($levels))->download('drought-water-levels-' . date('Ymd-His') . '.xlsx')
      
      Files: app/Http/Controllers/Back/WaterLevelController.php
      Verify: `php artisan tinker && app(\\App\\Http\\Controllers\\Back\\WaterLevelController::class)->export(request(['format' => 'excel']))` — pas d'erreur.

- [ ] 11. Ajouter méthode export() à ConsumptionReadingController
      Même pattern que WaterLevel, extract zone_id only, appel DroughtExportService::getConsumptionReadingsForExport(), vue 'back.drought.exports.consumption-pdf', fichier 'drought-consumption-readings-*.xlsx'.
      Files: app/Http/Controllers/Back/ConsumptionReadingController.php
      Verify: Méthode existe, pas d'erreur de compile.

- [ ] 12. Ajouter méthode export() à RestrictionController
      Extract zone_id, statut, type; appel DroughtExportService::getRestrictionsForExport(); vue 'back.drought.exports.restrictions-pdf'.
      Files: app/Http/Controllers/Back/RestrictionController.php
      Verify: Méthode existe.

- [ ] 13. Ajouter méthode export() à ScheduledCutController
      Extract zone_id, statut; appel DroughtExportService::getScheduledCutsForExport(); vue 'back.drought.exports.scheduled-cuts-pdf'.
      Files: app/Http/Controllers/Back/ScheduledCutController.php
      Verify: Méthode existe.

- [ ] 14. Créer vue PDF pour Water Levels
      Créer `resources/views/back/drought/exports/water-levels-pdf.blade.php` :
      - Orientation paysage (utiliser DomPDF avec orientation() dans le contrôleur ou CSS @page)
      - En-tête : "Rapport Niveaux des Ressources" | Date : {{ now()->format('d/m/Y H:i') }} | Gestionnaire : {{ Auth::user()->name }}
      - Tableau avec colonnes [Source, Type, Zone, Niveau, Seuils, Date] comme dans la vue index
      - Bordures tableau, couleurs de statut (critique, alerte, normal)
      - Pied de page : numéro de page
      
      Files: resources/views/back/drought/exports/water-levels-pdf.blade.php
      Verify: Fichier créé, pas d'erreur Blade.

- [ ] 15. Créer vue PDF pour Consumption Readings
      Créer `resources/views/back/drought/exports/consumption-pdf.blade.php` avec colonnes [Zone, Période, Consommation (m³), Abonnés, m³/Abonné].
      Files: resources/views/back/drought/exports/consumption-pdf.blade.php
      Verify: Fichier créé.

- [ ] 16. Créer vue PDF pour Restrictions
      Créer `resources/views/back/drought/exports/restrictions-pdf.blade.php` avec colonnes [Titre, Niveau, Zone, Période, Statut].
      Files: resources/views/back/drought/exports/restrictions-pdf.blade.php
      Verify: Fichier créé.

- [ ] 17. Créer vue PDF pour Scheduled Cuts
      Créer `resources/views/back/drought/exports/scheduled-cuts-pdf.blade.php` avec colonnes [Motif, Zone, Infrastructure, Période, Statut].
      Files: resources/views/back/drought/exports/scheduled-cuts-pdf.blade.php
      Verify: Fichier créé.

- [ ] 18. Ajouter boutons export à view water-levels/index.blade.php
      Ajouter deux boutons au-dessus du tableau : "Exporter en PDF" et "Exporter en Excel".
      Les URLs doivent inclure les filtres actuels : {{ route('back.water-levels.export', array_merge($filters, ['format' => 'pdf'])) }} où $filters = ['zone_id' => $currentZone, 'type_source' => $currentType] (non-null seulement).
      Style : boutons secondaires, icônes SVG (PDF, Excel).
      
      Files: resources/views/back/drought/levels/index.blade.php
      Verify: Voir la vue, pas d'erreur Blade, boutons visibles.

- [ ] 19. Ajouter boutons export à view consumption/index.blade.php
      Même pattern, filtres : zone_id; route 'back.consumption-readings.export'.
      Files: resources/views/back/drought/consumption/index.blade.php
      Verify: Boutons visibles.

- [ ] 20. Ajouter boutons export à view restrictions/index.blade.php
      Filtres : zone_id, statut, type; route 'back.restrictions.export'.
      Files: resources/views/back/drought/restrictions/index.blade.php
      Verify: Boutons visibles.

- [ ] 21. Ajouter boutons export à view cuts/index.blade.php
      Filtres : zone_id, statut; route 'back.scheduled-cuts.export'.
      Files: resources/views/back/drought/cuts/index.blade.php
      Verify: Boutons visibles.

---

## Vérification finale

- [ ] 22. Vérifier compilation et tests
      Exécuter `php artisan config:clear && php artisan config:cache`.
      Exécuter `php artisan test` — tous les tests passent (aucune régression).
      Files: none
      Verify: Exit code 0 pour les deux commandes.

- [ ] 23. Test manuel : Export PDF
      Dans le navigateur, accéder à `/back/water-levels` avec au moins un enregistrement.
      Appliquer un filtre (ex. zone_id=1).
      Cliquer sur "Exporter en PDF".
      Vérifier : téléchargement d'un fichier .pdf, contenu correspond aux données filtrées (zone 1 seulement).
      
      Files: none
      Verify: Fichier PDF téléchargé, contient les données filtrées.

- [ ] 24. Test manuel : Export Excel
      Cliquer sur "Exporter en Excel" sur la même page.
      Vérifier : téléchargement d'un fichier .xlsx, ouvrir dans Excel/Calc, en-têtes en gras + fond bleu, données formatées, colonnes larges.
      
      Files: none
      Verify: Fichier Excel valide, formatage respecté.

- [ ] 25. Répéter tests pour les 3 autres tables (Consumption, Restrictions, Cuts)
      Files: none
      Verify: Tous les exports PDF et Excel fonctionnent sur les 4 tables.

