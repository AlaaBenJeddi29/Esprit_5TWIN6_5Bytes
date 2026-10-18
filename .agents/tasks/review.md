# Export PDF et Excel pour Gestion Sécheresse

Implementation of PDF and Excel export functionality for drought management data (water levels, consumption readings, restrictions, scheduled cuts) with proper filtering, styling, and security.

This change introduces four export routes paired with corresponding Blade controllers, Laravel-Excel export classes with styling, and Blade PDF templates. Export routes accept zone, type, and status filters as query parameters, preserve filters through the export link, and enforce authentication via existing middleware. The service layer (DroughtExportService) applies filters consistently before passing data to export classes. Styling is implemented at the spreadsheet level (bold headers, colors, borders, column widths) and in Blade templates (status badges, visual hierarchy, landscape orientation).

**Watch for:** Filter parameters are passed through query strings in view links—verify they reach the export service queries without being dropped. PDF template status indicators (color-coded severity badges) are Blade-rendered and won't regenerate if data changes after export (expected behavior for a static document). Excel column widths assume French labels; if labels localize, widths may need adjustment.

**Verdict**: APPROVED

---

## High-level view

Export routes placed before resource declarations (standard pattern prevents routing conflicts). Each controller's export method extracts filters from request parameters, calls the service layer with those filters, and returns either an Excel download (using Maatwebsite) or PDF (using dompdf via Blade rendering). The service layer validates filter enums (e.g., checking that statut is in Restriction::STATUTS) to prevent query injection, logs empty results, and returns collections that get shaped into export rows. Excel classes map collection items to rows, apply header styling (bold white text on dark blue), borders, and alignment. PDF templates use HTML/CSS with embedded status styling (color-coded severity). Both formats handle missing relationships (e.g., null zone) with fallback "—" displays.

Filter passing works via `array_merge(request()->only([...]), ['format' => 'pdf|excel'])` in view links, which propagates active zone_id, type, statut filters into the export URL. The export routes then extract those same parameters and pass them to the service.

All export routes sit inside the `role:gestionnaire,admin` middleware group, inheriting authentication and role checks from the Back Office group. Export methods have no additional authorization logic (relied on parent middleware).

Empty datasets are handled gracefully: the service logs and returns empty collection, export classes handle @forelse with "Aucune donnée disponible" fallback, and Excel generates a header-only sheet.

---

<details>
<summary>Issues (5)</summary>

1. **Filter validation incomplete for WaterLevel type** — confirmed: `WaterLevel::getWaterLevelsForExport()` checks `array_key_exists($type, WaterLevel::TYPES)` but passes a string from `$request->string('type_source')`, which may never match if the query param name is inconsistent between index and export route. The index form uses `name="type_source"` but export extracts `type_source` from request—actually matches correctly. However, test an edge case: if a malformed/empty string reaches the export, it's logged as invalid but query still runs unfiltered, which is acceptable but slightly loose.

2. **Excel export does not set row alignment for numeric columns** — likely: ConsumptionReadingExport and WaterLevelExport calculate numeric values (m³/subscriber, percentages) but do not apply `Alignment::HORIZONTAL_RIGHT` to data cells. Headers are centered; data rows default to left alignment. This is a style inconsistency, not a functional bug. RestrictionExport and ScheduledCutExport also use default left alignment for dates.

3. **PDF templates reference Auth::user() without null coalesce fallback in one template** — confirmed: all four PDF templates use `{{ Auth::user()?->name ?? 'Système' }}`, which is safe. This is not an issue.

4. **ScheduledCutExport infrastructure nullable without default label** — confirmed: `$cut->infrastructure?->nom ?? '—'` handles null relationships. Both method calls are safe.

5. **Filters not re-passed in consumption-readings index** — confirmed: `consumption/index.blade.php` only passes `zone_id` filter to export, but `ConsumptionReadingExport` data set does not include type/statut filters because ConsumptionReading model has neither. This is correct—no issue.

</details>

---

<details>
<summary>Details</summary>

### Routing and filter preservation

Export routes are correctly placed before `Route::resource()` declarations in web.php, which prevents Laravel's implicit route parameter matching from intercepting export URIs (e.g., `/back/water-levels/export/pdf` would otherwise try to bind 'export' as a WaterLevel ID). The routes use named segments `export/{format}` and accept three query parameters: `zone_id`, `type` (or `type_source` for water levels), and `statut`. These parameters flow from view links via `array_merge(request()->only([...]), ['format' => 'pdf|excel'])`, preserving active filters.

The pattern works because Blade view links construct the URL with all currently active filters:
```blade
route('back.water-levels.export', array_merge(request()->only(['zone_id', 'type_source']), ['format' => 'pdf']))
```

The export controller then extracts the same parameters:
```php
$zoneId = $request->integer('zone_id') ?: null;
$type = $request->string('type_source')->toString() ?: null;
$format = $request->string('format')->toString() ?: 'pdf';
```

This ensures consistency. **Confirmed** that WaterLevelController uses `type_source` (matches form input name), ConsumptionReadingController uses only `zone_id` (correct, no other filters apply), RestrictionController uses `zone_id`, `statut`, `type` (correct), and ScheduledCutController uses `zone_id`, `statut` (correct). All views pass only the applicable filters for their data type.

### Filter validation and query safety

DroughtExportService validates enum values before applying them as query conditions:

```php
if ($statut && !array_key_exists($statut, Restriction::STATUTS)) {
    Log::warning('Invalid filter value for restriction statut export', [...]);
} else {
    $query->when($statut && array_key_exists($statut, Restriction::STATUTS), fn ($query) => $query->where('statut', $statut));
}
```

This pattern logs invalid values and falls back to an unfiltered query, avoiding SQL injection (Laravel's where() uses parameterized queries). The approach is defensive but slightly permissive: if a user manipulates a filter to an invalid enum, the export silently includes all records rather than returning an error. For a production system, this is acceptable (admin-level action, non-public-facing, graceful degradation). Empty results are logged at info level with filter values for audit trail.

**Confirmed** that all four service methods follow this pattern consistently. Water levels validate both `type` and `zone_id`, consumption readings validate only `zone_id`, restrictions validate `type`, `statut`, and `zone_id`, scheduled cuts validate `statut` and `zone_id`.

### Excel export styling

All four Export classes (WaterLevelExport, ConsumptionReadingExport, RestrictionExport, ScheduledCutExport) inherit styling via `WithStyles`, `WithColumnWidths`, and `WithHeadings`. Each class:

- Defines heading row (row 1) with bold white text (`'color' => ['rgb' => 'FFFFFF']`), dark blue background (`'rgb' => '1A5276'`), centered alignment, and thin black borders.
- Applies uniform borders to all data rows (rows 2 through highestRow).
- Sets column widths in a range 12–25 characters depending on expected content.
- Does NOT set alignment on data rows (defaults to left).

**Confirmed** columns are consistent with the collection mapping in each Export class (e.g., WaterLevelExport maps 7 columns: Source, Type, Zone, Niveau, Seuil Alerte, Seuil Critique, Date Mesure; columnWidths() defines widths for columns A–G). The widths are reasonable and match the French labels. 

One style gap: numeric data (percentages in WaterLevelExport, consumption in ConsumptionReadingExport, dates in all) would be more readable if right-aligned, but this is a UI preference, not a functional issue. Headers are centered; data rows inherit default left alignment from PhpOffice/PhpSpreadsheet.

### PDF templates and rendering

All four templates (water-levels-pdf, consumption-pdf, restrictions-pdf, scheduled-cuts-pdf) use HTML/CSS and are rendered by dompdf via `Pdf::loadView(). Each template:

- **Header**: Title (16px bold, brand color #1a5276), export timestamp, gestionnaire name. Gestionnaire field uses `{{ Auth::user()?->name ?? 'Système' }}` (safe null coalesce).
- **Table**: 100% width, border-collapse: collapse, landscape orientation (`@page { size: A4 landscape; margin: 15mm; }`), alternating row background colors (white / light gray), hover effect (CSS, ignored in PDF).
- **Status styling**: Water levels use `.status-critical`, `.status-warning`, `.status-normal` with color-coded backgrounds. Restrictions use `.level-{type}` (vigilance, alerte, alerte_renforcee, crise) and `.status-{statut}` (active, planned, lifted). Scheduled cuts use `.status-{statut}` (planifiee, en_cours, terminee, annulee). Styling is defined inline in the Blade template's `<style>` block.
- **Footer**: Generic footer with AquaSecure branding and generation timestamp.
- **Empty handling**: @forelse() loop with colspan fallback row "Aucune donnée disponible".

PDF templates are correctly structured for dompdf, with proper use of `@page` CSS to set orientation and margins. Status badges are rendered via `@switch()` conditional logic, mapping model attributes to CSS classes defined in the same template.

### Column correspondence (Excel and PDF)

**WaterLevelExport and water-levels-pdf:**
- Excel: Source, Type, Zone, Niveau (%), Seuil Alerte (%), Seuil Critique (%), Date Mesure
- PDF: Same 7 columns, plus a Statut column (8 total in PDF; Statut is rendered conditionally in PDF but not exported to Excel)
- Model attributes: nom_source, typeLabel(), zone->nom, niveau, seuil_alerte, seuil_critique, date_mesure, niveauStatut()
- **Confirmed** correspondence. PDF has an extra Statut column computed from niveauStatut(), which is not in Excel—acceptable trade-off (Excel focused on raw data, PDF adds derived status for readability).

**ConsumptionReadingExport and consumption-pdf:**
- Excel: Zone, Période, Consommation (m³), Abonnés, m³/Abonné (calculated)
- PDF: Same 5 columns
- Model attributes: zone->nom, periodeLabel(), consommation_m3, nombre_abonnes, calculated m³/subscriber
- **Confirmed** correspondence. Both calculate m³/subscriber in the same way (consommation_m3 / nombre_abonnes with fallback 0).

**RestrictionExport and restrictions-pdf:**
- Excel: Titre, Niveau (type), Zone, Date Début, Date Fin, Statut, Usages Interdits (7 columns)
- PDF: Same 7 columns
- Model attributes: titre, typeLabel(), zone->nom, date_debut, date_fin, statutLabel(), usages_interdits
- **Confirmed** correspondence. type displayed as typeLabel() for readability.

**ScheduledCutExport and scheduled-cuts-pdf:**
- Excel: Motif, Zone, Infrastructure, Date Début, Date Fin, Statut (6 columns)
- PDF: Same 6 columns
- Model attributes: motif, zone->nom, infrastructure->nom, date_debut, date_fin, statutLabel()
- **Confirmed** correspondence. Both handle null infrastructure with "—" fallback.

### Empty data handling

Each export method calls the service, which logs empty results. If data is empty:
- Excel export returns a sheet with headers only (first row populated, no data rows).
- PDF export renders the table with a single colspan'd row: `<tr><td colspan="N" class="text-center">Aucune donnée disponible</td></tr>`.

Both are user-friendly. No error is thrown; the download completes successfully with a clear "no data" message.

### Security and authorization

All export routes sit within the `Route::middleware(['auth', 'verified', 'role:gestionnaire,admin'])` group in web.php. This means:
- Export endpoints require authentication (middleware: auth, verified).
- Export endpoints require role check (middleware: role:gestionnaire,admin).
- No additional authorization is applied within the controller (export methods do not check role again or validate user permissions).

For this use case (admin and gestionnaire only, no per-resource permission checks), this is sufficient. A gestionnaire can export all zones, all restrictions, etc.—no row-level access control is enforced. This may be acceptable depending on business requirements; if gestionnaires should only export data for zones they manage, additional checks would be needed (not present in current code). **Confirmed** all four controllers inherit the parent middleware and have no additional checks.

### Route placement verification

Checking web.php structure:
```php
Route::get('water-levels/export/{format}', [WaterLevelController::class, 'export'])->name('water-levels.export');
Route::resource('water-levels', WaterLevelController::class)->names('water-levels')->except(['show'])->parameters(['water-levels' => 'level']);
```

The export route is declared first, before `Route::resource()`. This ensures that a GET request to `/back/water-levels/export/pdf` matches the export route, not the implicit show route that would try to bind 'export' as a WaterLevel ID. **Confirmed** all four export routes are placed before their corresponding resource declarations.

</details>

---

<details>
<summary>File map</summary>

- **routes/web.php** — Four export routes added within the Back Office auth group, placed before resource() declarations to prevent route-binding conflicts.
- **app/Services/Drought/DroughtExportService.php** — Four static methods extracting and filtering data: getWaterLevelsForExport(), getConsumptionReadingsForExport(), getRestrictionsForExport(), getScheduledCutsForExport(). Validates enums, logs results, returns collections.
- **app/Exports/Drought/WaterLevelExport.php** — Maatwebsite Excel export class, maps WaterLevel rows to 7 columns with calculated status, applies header styling (bold, blue bg, centered), borders, and column widths.
- **app/Exports/Drought/ConsumptionReadingExport.php** — Excel export for consumption readings, 5 columns (Zone, Période, m³, Abonnés, m³/Abonné), calculates per-subscriber value, applies header styling and borders.
- **app/Exports/Drought/RestrictionExport.php** — Excel export for restrictions, 7 columns (Titre, Niveau, Zone, Dates, Statut, Usages), applies header styling and borders.
- **app/Exports/Drought/ScheduledCutExport.php** — Excel export for scheduled cuts, 6 columns (Motif, Zone, Infra, Dates, Statut), applies header styling and borders.
- **resources/views/back/drought/exports/water-levels-pdf.blade.php** — Blade template for dompdf, renders table with status color coding (critical/alerte/normal), header with export date and gestionnaire, footer with timestamp.
- **resources/views/back/drought/exports/consumption-pdf.blade.php** — Blade template for consumption readings, same structure (header, table, footer), displays m³/subscriber calculated value.
- **resources/views/back/drought/exports/restrictions-pdf.blade.php** — Blade template for restrictions, status and level color coding, renders all restriction types and statuses.
- **resources/views/back/drought/exports/scheduled-cuts-pdf.blade.php** — Blade template for scheduled cuts, statut-based color coding (planifiee, en_cours, terminee, annulee).
- **app/Http/Controllers/Back/WaterLevelController.php** — Added export() method extracting zone_id, type_source, format; calls DroughtExportService; returns Excel or PDF download.
- **app/Http/Controllers/Back/ConsumptionReadingController.php** — Added export() method extracting zone_id, format; returns Excel or PDF.
- **app/Http/Controllers/Back/RestrictionController.php** — Added export() method extracting zone_id, statut, type, format; returns Excel or PDF.
- **app/Http/Controllers/Back/ScheduledCutController.php** — Added export() method extracting zone_id, statut, format; returns Excel or PDF.
- **resources/views/back/drought/levels/index.blade.php** — Modified to include export links: "📄 Exporter PDF" and "📊 Exporter Excel", passing active filters via query string.
- **resources/views/back/drought/consumption/index.blade.php** — Same export links, zone_id filter only (no type/statut).
- **resources/views/back/drought/restrictions/index.blade.php** — Same export links, zone_id, type, statut filters.
- **resources/views/back/drought/cuts/index.blade.php** — Same export links, zone_id, statut filters.

[Full diff available via git diff main]

</details>
