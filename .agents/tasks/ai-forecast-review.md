# Consumption Forecast Refactor: Static to Live Holt-Winters

The consumption forecast view now queries live predictions from a Holt-Winters service instead of a static database table. The controller's `forecast()` method invokes `ConsumptionForecastService->predictNextMonths()`, caches the result for 1 hour per zone, and invalidates the cache on data mutations (store, update, destroy). The Blade view consumes the new array-of-arrays structure and renders an interactive Chart.js visualization with historical data, forecasts, and confidence bounds (±15%).

**Watch for**: (1) **confirmed** — index safety on `$result['lower_bounds'][$i]` and `$upper_bounds[$i]` when `$result['predictions']` is empty; (2) **likely** — cache invalidation completeness (all mutations on the zone covered, no orphaned cache entries); (3) **confirmed** — view data mapping correctness (all old model properties replaced with new array keys).

**Verdict**: NEEDS_CHANGES

---

## High-level view

The forecast controller now calls a live Holt-Winters algorithm to compute 6-month consumption predictions per zone, builds labeled forecast points with upper and lower confidence bounds, and caches the result for 1 hour. Cache is cleared whenever a consumption reading is created, updated, or deleted for that zone. The refactor preserves the existing risk analysis and historical data flow, adding only the live forecast computation layer.

The view constructs historical data from the last 12 consumption readings and forecast data from the live algorithm output, then passes both to a Chart.js instance that renders a dual-line chart (historical solid, forecast dashed) with a confidence band. A window selector lets users zoom to 12, 18, or 24 months or see all data. The design keeps all Tailwind dark-mode styling consistent with the surrounding page.

---

<details>
<summary>Issues (3)</summary>

1. **Empty predictions handling** — When `result['predictions']` is empty (fewer than 12 historical data points), `lower_bounds[$i]` and `upper_bounds[$i]` fallback to 0 via the null-coalescing operator. This works but produces an empty `forecastPoints` array, which is then checked in the view with `@if ($forecastData->count() > 0)`. Verify no silent failures occur upstream if consumers assume non-empty forecasts.

2. **Chart windowing logic asymmetry** — The JavaScript windowing logic slices forecast values starting from `startIdx - forecastStart`, which can be negative and is then corrected with `Math.max(0, ...)`. Edge case: if only 1–2 historical points exist and a large window is selected, the slice indices may produce unexpected empty arrays. The `historicalValues.length` boundary isn't explicitly guarded against forecasts extending beyond window bounds.

3. **Missing Chart.js library guarantee** — The view inlines a Chart.js instance creation but does not verify that the Chart library is loaded before calling `new Chart(ctx, ...)`. If the Chart library is deferred or fails to load, the script will throw a reference error and silently fail (no user-facing error). No fallback or loading check is present.

</details>

---

## Cache invalidation strategy

The forecast cache key is `forecast_zone_{zone_id}` and is set to expire in 1 hour. Three mutations trigger cache invalidation: `store()` captures the created reading's zone_id and calls `Cache::forget()`, `update()` clears the cache for the reading's zone before updating, and `destroy()` saves the zone_id before deletion, then clears the cache. This strategy is complete for the consumption readings table, the only source of forecast data. The Holt-Winters model depends on historical consumption values, not zone metadata, so cache invalidation on metadata changes is unnecessary.

---

## Forecast data structure and array access safety

The controller iterates over `$result['predictions']` and builds forecast points with array indices `$i`, accessing `$result['lower_bounds'][$i]` and `$result['upper_bounds'][$i]` with null-coalescing fallback to 0. When insufficient data exists (`count($readings) < 12`), the service returns empty arrays for all three keys. The foreach loop doesn't execute, `forecastPoints` remains empty, and the view shows "Données insuffisantes" instead of attempting to render a chart. The null-coalescing operator is defensive but never triggered in practice because the service always returns arrays of equal length. No off-by-one risk: the loop counter `$i` stays within bounds.

---

## View data transformation and consumption by Chart.js

The view receives `forecastPoints` as a collection of associative arrays (keys: `month`, `date`, `value`, `lower`, `upper`), transforms them into two parallel structures: `$historicalData` from zone consumption readings and `$forecastData` from forecast points. The Chart.js script json_encode's the data into JavaScript arrays for values, bounds, and labels, then slices them symmetrically for the windowing view. The historical line uses the first `historicalValues.length` positions; the forecast line starts after. Confidence bands overlay as semi-transparent fills.

All old references to static model properties (`->prediction_m3`, `->lower_bound`, etc.) have been replaced with array keys. No stale field references remain.

---

## Interactive chart initialization and dark-mode support

The view embeds a Chart.js instance inside a conditional `<script>` block that only renders if data exists. The script is executed on DOMContentLoaded, ensuring the canvas element is in the DOM. Dark mode is detected via `document.documentElement.classList.contains('dark')`, and text/grid colors are adjusted accordingly. All Tailwind dark-mode classes in the surrounding HTML are preserved, so the card and controls remain visually cohesive. If dark mode is toggled after the chart is rendered, the chart colors don't update; this requires listening to a dark-mode event, which is outside this change's scope.

---

## Service algorithm and caching strategy

The `ConsumptionForecastService->predictNextMonths()` implements Holt-Winters exponential smoothing with alpha=0.3, beta=0.1, gamma=0.1, and a 12-month seasonal period. It trains on the last 24 months (or fewer if unavailable) and produces 6 predictions with ±15% confidence bounds. The service is called once per zone per view render, with results cached for 1 hour per zone. Cache is invalidated by data mutations, so forecasts refresh immediately when readings change but remain stable between updates. This granularity prevents redundant computation while keeping predictions current. MAE is calculated using the last 3 months of training data as hold-out validation, ensuring unbiased error estimates.

---

## Test coverage gaps

No new tests are visible in the diff. Critical gaps: no unit tests for `ConsumptionForecastService->predictNextMonths()` covering edge cases (0 readings, 1–11 readings, exactly 12 readings, 25+ readings); no test for cache invalidation behavior on store/update/destroy; no integration test for view rendering with live forecast data. The algorithm (Holt-Winters parameter tuning, seasonal index initialization) isn't validated, leaving no safety net for future parameter changes.

---

## File map

<details>
<summary>Files changed</summary>

- **ConsumptionReadingController.php** — Refactored `forecast()` to call `ConsumptionForecastService` and cache results. Added cache invalidation to `store()`, `update()`, `destroy()`. Added constructor dependency injection for `ConsumptionForecastService` and cache facade.
- **forecast.blade.php** — Replaced static forecast display with live data mapping, added Chart.js visualization with windowing controls, added statistics row (current consumption, averages, trend, MAE), updated description to reference Holt-Winters algorithm, added "IA en direct" badge.

Full diff available via `git diff HEAD`.

</details>
