# Implementation Plan: AI/ML Water Consumption Forecasting (Gestion 4)

**Task ID:** ai-forecast-implementation  
**Project:** AquaSecure — Drought Management & Continuity of Service  
**Framework:** Laravel 12 / MySQL 8 / Tailwind CSS 4 / PHP 8.2+

---

## Context Summary

### Current State
- **Forecast Model:** Linear regression (basic, no seasonality) in `DroughtAnalyzer::forecast()`
- **Data Availability:** 15 consumption readings across 5 zones (~3 months Aug–Oct 2026)
- **Route:** GET `/back/consumption-readings/previsions` → existing forecast view
- **Queue System:** Database queue + mail configured; job infrastructure ready
- **Styling:** Tailwind CSS 4, dark mode support, French labels, established component patterns
- **No existing:** Chart.js in dependencies, Console/Commands directory, API routes, ConsumptionForecast model/table

### Architecture Decisions
1. **Holt-Winters Algorithm (PHP):** Pure PHP implementation of exponential smoothing with seasonal components (no Python, no external ML services). Rationale: Captures seasonality (summer peak water usage), handles trend changes, provides confidence intervals naturally.
2. **24-Month Synthetic Dataset:** Jan 2025 – Dec 2026. Rationale: Time series ML needs >12 months; synthetic data ensures consistent patterns without external dependencies.
3. **Separate ConsumptionForecastService:** Keep existing `DroughtAnalyzer::forecast()` untouched (used by `riskScore()`); new service handles persistent DB storage and advanced algorithms.
4. **Scheduled Command + Queued Job:** Daily regeneration at 23:00 via artisan command; individual forecast generation queued. Rationale: Allows long-running predictions without blocking HTTP.
5. **API Endpoint:** New `/api/v1/drought/zones/{id}/consumption/forecast` for future mobile/external integrations; returns forecast + historical + quality metrics.
6. **Interactive Chart.js:** Dual-line graph (solid historical, dashed forecast), confidence band, responsive, dark mode. Rationale: Matches existing Blade/Tailwind patterns; Chart.js is lightweight and standard.

---

## Deliverables Checklist

### 1. Database & Seeding
- [ ] Migration: `create_consumption_forecasts_table` (id, zone_id, forecast_month, prediction_m3, lower_bound, upper_bound, model_used, mae, created_at, updated_at)
- [ ] Seeder: `ConsumptionHistorySeeder` — 24 months synthetic data (Jan 2025 – Dec 2026), realistic seasonal variance

### 2. Models
- [ ] `app/Models/Drought/ConsumptionForecast.php` — Eloquent model with Zone relation

### 3. Core Forecasting Logic
- [ ] `app/Services/ConsumptionForecastService.php` — `predictNextMonths(Zone $zone, int $months = 6): array` using Holt-Winters algorithm; generates predictions + confidence intervals (±15%) + MAE

### 4. Background Jobs & Scheduling
- [ ] `app/Jobs/GenerateConsumptionForecasts.php` — Queued job to compute and store forecasts for a zone
- [ ] `app/Console/Commands/RefreshForecasts.php` — Artisan command `drought:refresh-forecasts`, dispatches jobs for all zones
- [ ] `app/Console/Kernel.php` — Schedule command daily at 23:00

### 5. API
- [ ] `routes/api.php` — New file with `GET /api/v1/drought/zones/{id}/consumption/forecast` endpoint
- [ ] `app/Http/Controllers/Api/V1/DroughtController.php` — Returns JSON response with forecast, historical, confidence, MAE

### 6. Frontend
- [ ] Update `resources/views/back/drought/consumption/forecast.blade.php` — Replace static bars with Chart.js interactive graph; add historical + forecast + confidence band
- [ ] Update `package.json` — Add Chart.js dependency
- [ ] Build CSS/JS via npm

### 7. Service Integration
- [ ] Modify `app/Services/DroughtAnalyzer.php` — Update `riskScore()` to consider ConsumptionForecast DB data (if available) in risk calculation

---

## Implementation Sequence (By Dependency)

### Phase 1: Data Foundation
**1. Create ConsumptionHistorySeeder**
   - **What:** Generate 24 months of synthetic consumption data (Jan 2025 – Dec 2026) across 5 zones.
   - **Why:** ML forecasting needs seasonal baseline; 3 months is insufficient for pattern learning.
   - **Pattern:** Realistic monthly variation (summer peak +20%, winter baseline), per-zone scaling.
   - **Files:** `database/seeders/ConsumptionHistorySeeder.php`
   - **Verify:** Run `php artisan db:seed --class=ConsumptionHistorySeeder` and confirm 120 rows (5 zones × 24 months) in `consumption_readings` table.

**2. Create ConsumptionForecast Migration**
   - **What:** New table to persist forecast outputs (predictions, bounds, model metadata).
   - **Why:** Required for historical comparison, trend analysis, and API queries.
   - **Schema:** id, zone_id (FK), forecast_month (DATE unique per zone), prediction_m3, lower_bound, upper_bound, model_used ENUM('linear','holt_winters','seasonal'), mae, created_at, updated_at.
   - **Files:** `database/migrations/2026_10_18_XXX_create_consumption_forecasts_table.php`
   - **Verify:** Run `php artisan migrate` and confirm table exists with correct columns.

**3. Create ConsumptionForecast Model**
   - **What:** Eloquent model with Zone relation and attribute casts.
   - **Why:** Type safety and ORM convenience for forecast queries.
   - **Relations:** `belongsTo(Zone)`, inverse `hasMany(ConsumptionForecast)` on Zone.
   - **Casts:** prediction_m3, lower_bound, upper_bound as decimal:2; forecast_month as date.
   - **Files:** `app/Models/Drought/ConsumptionForecast.php`
   - **Verify:** Tinker: `App\Models\Drought\ConsumptionForecast::count() === 0` (empty table before forecast run).

---

### Phase 2: Forecasting Engine
**4. Create ConsumptionForecastService**
   - **What:** Core algorithm — Holt-Winters exponential smoothing with seasonality (PHP-only).
   - **Why:** Outperforms linear regression on seasonal data; naturally produces intervals.
   - **Algorithm Outline:**
     - Input: Zone + historical readings (12+ months), forecast months (default 6).
     - Level smoothing: α = 0.3, Trend smoothing: β = 0.1, Seasonal smoothing: γ = 0.1.
     - Seasonal period: 12 months (annual pattern).
     - Output: `['predictions' => [...], 'lower_bounds' => [...], 'upper_bounds' => [...], 'mae' => float, 'model' => 'holt_winters']`.
   - **Confidence Intervals:** ±15% of predicted value (lower = pred × 0.85, upper = pred × 1.15).
   - **Error Metric:** MAE computed from hold-out validation (last 3 months of historical vs. predicted).
   - **Files:** `app/Services/ConsumptionForecastService.php`
   - **Verify:** Unit test or manual: `$service->predictNextMonths($zone, 6)` returns valid array with 6 forecast months, all bounds > 0, MAE ≥ 0.

**5. Create GenerateConsumptionForecasts Job**
   - **What:** Queued job that computes forecasts for one zone and stores them in DB.
   - **Why:** Offloads long computation from HTTP request; allows batching.
   - **Dispatch:** Called by command; each zone → one job.
   - **Logic:**
     - Receive Zone.
     - Call `ConsumptionForecastService::predictNextMonths($zone, 6)`.
     - Upsert results into `consumption_forecasts` table (forecast_month + zone_id unique).
     - Dispatch next zone (or let scheduler dispatch all in parallel).
   - **Files:** `app/Jobs/GenerateConsumptionForecasts.php`
   - **Verify:** Enqueue manually via Tinker, process queue, confirm rows in DB.

**6. Create RefreshForecasts Command**
   - **What:** Artisan command `drought:refresh-forecasts` to regenerate all forecasts.
   - **Why:** Scheduled nightly to keep predictions fresh without manual intervention.
   - **Logic:**
     - Fetch all zones.
     - For each zone, dispatch `GenerateConsumptionForecasts` job or call synchronously (if fast enough).
     - Output success message with count.
   - **Files:** `app/Console/Commands/RefreshForecasts.php`
   - **Verify:** Run `php artisan drought:refresh-forecasts` and check output; rows appear in `consumption_forecasts`.

**7. Schedule RefreshForecasts in Kernel**
   - **What:** Add to `app/Console/Kernel.php` schedule: `$schedule->command('drought:refresh-forecasts')->dailyAt('23:00')`.
   - **Why:** Ensures forecasts regenerate automatically at low-traffic time (11 PM).
   - **Files:** `app/Console/Kernel.php`
   - **Verify:** Check artisan `schedule:list` shows the command; (no live test needed, scheduling only verifiable in logs after 23:00).

---

### Phase 3: API
**8. Create API Routes File**
   - **What:** New `routes/api.php` with middleware: `api` (no auth for public drought data), versioning prefix `/api/v1`.
   - **Why:** Separates API concerns from web routes; standard REST convention.
   - **Routes:**
     ```php
     Route::prefix('v1')->name('api.v1.')->group(function () {
         Route::prefix('drought')->name('drought.')->group(function () {
             Route::get('zones/{zone}/consumption/forecast', [DroughtController::class, 'forecastZone'])->name('forecast');
         });
     });
     ```
   - **Files:** `routes/api.php`
   - **Verify:** `php artisan route:list | grep /api/v1` shows the endpoint registered.

**9. Create API DroughtController**
   - **What:** Controller class for drought API endpoints.
   - **Why:** Handles JSON serialization, error handling, response formatting.
   - **Method: `forecastZone(Zone $zone)`:**
     - Fetch zone with latest consumption readings (12 months).
     - Fetch latest forecasts (6 months) from DB.
     - Compute MAE + quality score.
     - Return JSON: `{ zone, historical_data: [...], forecast: [...], confidence_intervals: {...}, quality_metrics: {...} }`.
   - **Files:** `app/Http/Controllers/Api/V1/DroughtController.php`
   - **Verify:** Call `GET /api/v1/drought/zones/1/consumption/forecast` and confirm JSON response with correct schema.

---

### Phase 4: Frontend
**10. Update package.json & Install Chart.js**
   - **What:** Add `chart.js` to `dependencies`.
   - **Why:** Required for interactive forecasting graphs.
   - **Files:** `package.json`
   - **Verify:** Run `npm install` and confirm `Chart.js` in `node_modules`.

**11. Update Forecast View with Interactive Chart**
   - **What:** Replace static bar chart in `resources/views/back/drought/consumption/forecast.blade.php` with Chart.js dual-line graph.
   - **Why:** Interactivity (hover, zoom), confidence band visual, matches design consistency.
   - **Graph Elements:**
     - X-axis: Months (last 12 historical + 6 forecast).
     - Y-axis: Consumption (m³).
     - Line 1 (solid, blue): Historical data.
     - Line 2 (dashed, green): Forecast predictions.
     - Shaded area: Confidence band (lower–upper bounds).
     - Legend: Historical, Forecast, Confidence Band.
     - Tooltip: Month, value, type.
     - Responsive: Full-width in grid, mobile-friendly.
     - Dark mode: Adapted text/grid colors.
   - **Data Passed from Controller:** Modify `ConsumptionReadingController::forecast()` to also fetch ConsumptionForecast rows and pass to view.
   - **Files:** 
     - `resources/views/back/drought/consumption/forecast.blade.php`
     - `resources/js/app.js` or new `resources/js/components/forecast-chart.js`
   - **Verify:** Load page `/back/consumption-readings/previsions`, confirm Chart.js renders; inspect browser console for no errors.

**12. Update DroughtAnalyzer::riskScore()**
   - **What:** Integrate ConsumptionForecast data into risk scoring.
   - **Why:** Makes predictions actionable: if forecast predicts severe shortage, increase risk.
   - **Logic Addition:**
     - Fetch latest forecast for zone.
     - If prediction shows >15% decline over 6 months, add 10 points to score.
     - If lower_bound < critical threshold, add 15 points.
   - **Files:** `app/Services/DroughtAnalyzer.php`
   - **Verify:** Unit test or manual: call `riskScore()` for a zone with forecast; score should reflect forecast trend.

---

## Testing & Verification Strategy

### Unit Tests (if time permits)
- `tests/Unit/Services/ConsumptionForecastServiceTest.php` — Holt-Winters algorithm correctness.
- `tests/Unit/Jobs/GenerateConsumptionForecastsJobTest.php` — Job dispatch + DB upsert.

### Manual Verification Steps
1. **Database:**
   ```bash
   php artisan migrate
   php artisan db:seed --class=ConsumptionHistorySeeder
   # Verify: SELECT COUNT(*) FROM consumption_readings; -- should be 120+
   ```

2. **Forecast Generation:**
   ```bash
   php artisan drought:refresh-forecasts
   # Verify: SELECT COUNT(*) FROM consumption_forecasts; -- should be 5 zones × 6 months = 30
   # Check values: SELECT * FROM consumption_forecasts LIMIT 5;
   ```

3. **API Test:**
   ```bash
   curl http://localhost:8000/api/v1/drought/zones/1/consumption/forecast | jq .
   # Expected: { zone: {...}, historical_data: [...], forecast: [...], confidence_intervals: {...} }
   ```

4. **Frontend:**
   - Navigate to `/back/consumption-readings/previsions`.
   - Confirm Chart.js renders with historical + forecast lines + confidence band.
   - Check dark mode toggle works (class `dark:` styling applied).
   - Inspect French labels (month names in translatedFormat).

5. **Risk Score Integration:**
   - Load a zone detail page.
   - Confirm risk score reflects forecast (if declining trend, score higher).

---

## Technical Notes

### Holt-Winters Pseudocode (for review)
```
Input: readings (historical monthly values, 12+ items)
       seasonal_period = 12
       α (level), β (trend), γ (seasonal smoothing)

Initialize:
  L = mean(readings[0:seasonal_period])
  T = (sum(readings[seasonal_period:2*seasonal_period]) - sum(readings[0:seasonal_period])) / seasonal_period^2
  S = [readings[i] / L for i in 0..seasonal_period] -- seasonal indices

Update (for each new reading):
  L' = α * (reading / S[t-seasonal_period]) + (1-α) * (L + T)
  T' = β * (L' - L) + (1-β) * T
  S' = γ * (reading / L') + (1-γ) * S[t-seasonal_period]

Forecast (m steps ahead):
  pred[t+m] = (L + m*T) * S[t+m-seasonal_period]
  confidence_lower = pred[t+m] * 0.85
  confidence_upper = pred[t+m] * 1.15

MAE = mean(abs(predicted[holdout] - actual[holdout]))
```

### Synthetic Data Generation Pattern (for review)
```
Base monthly consumption per zone: 10,000–50,000 m³ (vary by population)
Seasonal multiplier: 
  - Summer (Jun–Aug): +20% (more outdoor usage)
  - Spring/Fall (Mar–May, Sep–Nov): baseline
  - Winter (Dec–Feb): -10% (lower usage)
Random noise: ±5% per reading
```

### File Structure After Implementation
```
AquaSecure/
├── app/
│   ├── Console/
│   │   ├── Commands/
│   │   │   └── RefreshForecasts.php
│   │   └── Kernel.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   └── V1/
│   │   │   │       └── DroughtController.php
│   │   │   └── Back/
│   │   │       └── ConsumptionReadingController.php (modified)
│   ├── Jobs/
│   │   └── GenerateConsumptionForecasts.php
│   ├── Models/
│   │   └── Drought/
│   │       ├── ConsumptionForecast.php
│   │       └── ... (existing)
│   └── Services/
│       ├── ConsumptionForecastService.php
│       └── DroughtAnalyzer.php (modified)
├── database/
│   ├── migrations/
│   │   └── 2026_10_18_XXX_create_consumption_forecasts_table.php
│   └── seeders/
│       ├── ConsumptionHistorySeeder.php
│       └── ... (existing)
├── resources/
│   └── views/
│       └── back/
│           └── drought/
│               └── consumption/
│                   └── forecast.blade.php (modified)
├── routes/
│   ├── api.php (new)
│   └── web.php
└── package.json (modified)
```

---

## Known Constraints & Assumptions

1. **No External ML/Python:** All algorithms in PHP; no sklearn, no async Python workers.
2. **Synthetic Historical Data:** 24 months of realistic but generated readings (ensures consistent training baseline).
3. **6-Month Forecast Horizon:** Default; configurable per call.
4. **Confidence Intervals:** Fixed ±15%; could be refined to actual std dev if needed.
5. **Queue Driver:** Database; assumes queue:listen is running in dev (or supervisor in production).
6. **Timezone:** All dates in app timezone (from config/app.php); chart.js respects browser locale for month labels via translatedFormat().
7. **No Auth on API:** `/api/v1/drought/zones/...` is public; if future requirement demands auth, add middleware.

---

## Completion Criteria

✅ Implementation is complete when:
1. All 12 deliverables above are implemented.
2. Database contains 120+ consumption readings (synthetic 24-month seeder) + ≥30 forecast records.
3. `/back/consumption-readings/previsions` displays interactive Chart.js graph with historical + forecast + confidence band.
4. `/api/v1/drought/zones/1/consumption/forecast` returns valid JSON with all required fields.
5. `php artisan drought:refresh-forecasts` runs without errors and updates all zones' forecasts.
6. Risk score integrates forecast data (reflected in riskScore() calculation).
7. No breaking changes to existing `DroughtAnalyzer::forecast()` or UI/UX.
8. Dark mode & French labels preserved throughout.

