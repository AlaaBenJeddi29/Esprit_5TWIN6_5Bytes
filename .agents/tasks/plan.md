# Implementation Plan: Email Notification System for Alert Subscriptions

## Project Context
- **Framework**: Laravel 12, PHP 8.2
- **Database**: MySQL via XAMPP
- **Mail Config**: Currently set to `MAIL_MAILER=log` in .env.example (logs to file in dev)
- **Queue Connection**: Database (QUEUE_CONNECTION=database)
- **Relevant Models**: User, AlertSubscription, WaterQualityAlert, Restriction, ScheduledCut, Zone
- **Column Confirmations** (from code inspection):
  - AlertSubscription: id, user_id, zone_id, type_alerte (enum: 'tous', 'qualite', 'restriction', 'coupure'), actif, timestamps
  - User: id, email, name, role, is_active, timestamps
  - Zone: id, nom, commune, code_postal, population, latitude, longitude, timestamps
  - WaterQualityAlert: id, zone_id, sample_id, parametre, niveau, message, statut, resolue_at, timestamps (no title/description — uses message field)
  - Restriction: id, zone_id, type, titre, description, usages_interdits, date_debut, date_fin, statut, timestamps
  - ScheduledCut: id, zone_id, infrastructure_id, motif, date_debut, date_fin, statut, timestamps (no title/description — uses motif)

## Implementation Steps

### 1. Create alert_logs table migration
**Purpose**: Track sent notifications to prevent duplicates and for audit/history.

**Files to Create**:
- `database/migrations/2026_10_XX_XXXXXX_create_alert_logs_table.php`

**What to Do**: Create migration with columns: id, user_id (FK to users), zone_id (FK to zones), alert_type (enum: 'qualite', 'restriction', 'coupure'), alert_id (nullable, references the specific alert record), titre, description, sent_at (timestamp), created_at. Add indexes on user_id+zone_id+alert_type+alert_id for deduplication checks. The alert_id field stores the PK of WaterQualityAlert/Restriction/ScheduledCut to uniquely identify which alert triggered the send.

**Verify**: Run `php artisan migrate --fresh` — migration applies without errors, table structure correct via `DESCRIBE alert_logs` in MySQL.

---

### 2. Create SubscriptionConfirmationMail mailable
**Purpose**: Sent when a citizen creates an AlertSubscription.

**Files to Create**:
- `app/Mail/SubscriptionConfirmationMail.php`

**What to Do**: Build a Mailable class. Constructor accepts AlertSubscription instance. Use `queued()` method to dispatch to queue. In `envelope()`, set `subject` to "Confirmation d'abonnement aux alertes de zone" and `from()` to config('mail.from.address'). In `content()`, pass the subscription and related zone/user to the view. Add markdown view reference: `'emails.subscription-confirmation'`.

**Verify**: Class exists in correct namespace, has queued() method, envelope() with French subject, accepts AlertSubscription in constructor, references blade view.

---

### 3. Create AlertNotificationMail mailable
**Purpose**: Sent to all zone subscribers when a new water quality alert, restriction, or scheduled cut is created.

**Files to Create**:
- `app/Mail/AlertNotificationMail.php`

**What to Do**: Build a Mailable. Constructor accepts: user (User instance), zone (Zone instance), alertType (string: 'qualite', 'restriction', 'coupure'), titre (string), description (string), and optional extra details. Use `queued()`. In `envelope()`, set subject to alert summary, e.g., "Alerte zone: {zone.nom}". In `content()`, pass all data to view. Reference markdown view `'emails.alert-notification'`.

**Verify**: Class exists, accepts all required parameters, uses queued(), has proper envelope with subject, references blade view.

---

### 4. Create subscription-confirmation email blade view
**Purpose**: Professional HTML email template for subscription confirmation.

**Files to Create**:
- `resources/views/emails/subscription-confirmation.blade.php`

**What to Do**: Create a blade file using Laravel mail markdown syntax (starts with `@component('mail::message')`). Include:
  - Greeting: "Bienvenue {user.name}!"
  - Body explaining subscription created for {zone.nom}
  - Subscription details: zone name, alert type label (use AlertSubscription::TYPES)
  - Footer with unsubscribe link (hint: link to alert-subscriptions edit page with zone_id)
  - Action button: "Gérer mes abonnements" linking to /front/alert-subscriptions
  - Close with @endcomponent

**Verify**: File exists at correct path, uses @component('mail::message'), includes all required content, no syntax errors when blade compiles.

---

### 5. Create alert-notification email blade view
**Purpose**: Professional HTML email template for alert notifications.

**Files to Create**:
- `resources/views/emails/alert-notification.blade.php`

**What to Do**: Create a blade file. Include:
  - Greeting: "Alerte pour votre zone {zone.nom}"
  - Alert title and description
  - Alert details (alert type label, date/time, severity if applicable)
  - Action button: "Consulter l'alerte" with link to back office or front portal (use generic alert management page or mail detail page)
  - Footer: "Vous avez reçu cet email car vous êtes abonné aux alertes de {zone.nom}"
  - Unsubscribe hint
  - Close with @endcomponent

**Verify**: File exists, uses @component, includes all required alert details and user-facing text.

---

### 6. Create AlertSubscriptionObserver
**Purpose**: Hook into AlertSubscription model lifecycle to send confirmation email on create/update.

**Files to Create**:
- `app/Observers/AlertSubscriptionObserver.php`

**What to Do**: Create observer class with `created()` method. When a new subscription is created (check `wasRecentlyCreated` or compare timestamps), dispatch SubscriptionConfirmationMail to the user's email via Mail::queue(). Only fire on creation, not on update.

**Verify**: Observer file exists with created() method, dispatches SubscriptionConfirmationMail correctly.

---

### 7. Create WaterQualityAlertObserver
**Purpose**: Hook into WaterQualityAlert model to notify all zone subscribers when new alert created.

**Files to Create**:
- `app/Observers/WaterQualityAlertObserver.php`

**What to Do**: Create observer with `created()` method. When new WaterQualityAlert created:
  1. Find all active AlertSubscription records where zone_id matches AND (type_alerte = 'tous' OR type_alerte = 'qualite')
  2. For each subscription, check if alert already logged in alert_logs (prevent duplicate sends)
  3. If not logged, dispatch SendAlertNotificationJob (see step 8) with all necessary data
  4. Log the alert send to alert_logs table

Alternatively, dispatch job and let job handle everything (preferred: cleaner separation).

**Verify**: Observer exists, created() method dispatches SendAlertNotificationJob for each matching subscription.

---

### 8. Create RestrictionObserver
**Purpose**: Hook into Restriction model to notify zone subscribers on create.

**Files to Create**:
- `app/Observers/RestrictionObserver.php`

**What to Do**: Create observer with `created()` method. When new Restriction created:
  1. Find all active AlertSubscription where zone_id matches AND (type_alerte = 'tous' OR type_alerte = 'restriction')
  2. Dispatch SendAlertNotificationJob for each subscriber
  3. Similar to WaterQualityAlertObserver (see step 7)

**Verify**: Observer exists with created() method, matches subscription logic from WaterQualityAlertObserver.

---

### 9. Create ScheduledCutObserver
**Purpose**: Hook into ScheduledCut model to notify zone subscribers on create.

**Files to Create**:
- `app/Observers/ScheduledCutObserver.php`

**What to Do**: Create observer with `created()` method. When new ScheduledCut created:
  1. Find all active AlertSubscription where zone_id matches AND (type_alerte = 'tous' OR type_alerte = 'coupure')
  2. Dispatch SendAlertNotificationJob for each subscriber
  3. Similar pattern to previous observers

**Verify**: Observer exists with created() method, matches subscription logic.

---

### 10. Create SendAlertNotificationJob
**Purpose**: Async job to send alert notifications (queued, database driver).

**Files to Create**:
- `app/Jobs/SendAlertNotificationJob.php`

**What to Do**: Create a queueable job. Constructor accepts:
  - $userId (user receiving notification)
  - $zoneId
  - $alertType ('qualite', 'restriction', 'coupure')
  - $alertId (PK of the specific alert record)
  - $titre (alert title)
  - $description (alert description)
  - $extraData (optional array for extra context)

In `handle()` method:
  1. Find User by $userId (abort if not found)
  2. Find Zone by $zoneId (abort if not found)
  3. Check alert_logs table for existing entry (user_id, zone_id, alert_type, alert_id all match) — if found, return (already sent)
  4. If not found, record to alert_logs with sent_at = now()
  5. Dispatch AlertNotificationMail via Mail::queue()
  6. Log success or catch and rethrow exceptions

Add `ShouldQueue` interface, set `public $queue = 'default'`.

**Verify**: Job exists, implements ShouldQueue, accepts all required parameters, handles logging to alert_logs.

---

### 11. Register all observers in AppServiceProvider
**Purpose**: Connect observers to model events.

**Files to Modify**:
- `app/Providers/AppServiceProvider.php`

**What to Do**: In `boot()` method, add observer registrations:
```php
AlertSubscription::observe(AlertSubscriptionObserver::class);
WaterQualityAlert::observe(WaterQualityAlertObserver::class);
Restriction::observe(RestrictionObserver::class);
ScheduledCut::observe(ScheduledCutObserver::class);
```

Import all observer classes at top of file.

**Verify**: AppServiceProvider boot() method includes all observer registrations, no syntax errors, models are imported.

---

### 12. Update .env.example with mail configuration
**Purpose**: Document mail setup for new developers.

**Files to Modify**:
- `.env.example`

**What to Do**: Ensure MAIL_* variables are present (they already are). Add comments explaining:
- MAIL_MAILER=log uses file logging in dev (good for testing without actual mail server)
- For production, change to MAIL_MAILER=smtp and configure MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD
- Clarify QUEUE_CONNECTION=database means jobs stored in jobs table and executed by queue:listen
- MAIL_FROM_ADDRESS should be a no-reply address

**Verify**: .env.example has all MAIL_* vars with explanatory comments above them.

---

### 13. Verify no changes needed to AlertSubscriptionController
**Purpose**: Confirm controller store() method correctly triggers observer.

**Files to Review**:
- `app/Http/Controllers/Front/AlertSubscriptionController.php`

**What to Do**: Read store() method. It uses `AlertSubscription::updateOrCreate()` which triggers `created()` event only on new creation (not on update). This is correct — observer will fire automatically. No changes needed. Document in implementation that the observer hooks into model events, not the controller.

**Verify**: store() uses updateOrCreate(), which triggers created event, observer will auto-fire — no code changes required.

---

## Verification Steps (Run After All Implementation)

1. **Database**: Run `php artisan migrate --fresh` to create alert_logs table.
2. **Build**: Run `php artisan tinker` and confirm all new classes load without errors.
3. **Manual Test**:
   - Create a test user + zone + AlertSubscription via tinker or seeder
   - Confirm SubscriptionConfirmationMail sent and logged (check storage/logs or mail.log)
   - Create WaterQualityAlert for that zone
   - Confirm SendAlertNotificationJob queued and executed
   - Check jobs table and alert_logs table for records
4. **Queue Testing**: Run `php artisan queue:listen` in background, repeat manual test.
5. **Unit Tests**: Run `php artisan test --filter=mail` or `php artisan test --filter=queue` (if tests created).

---

## Notes

- **No title/description in WaterQualityAlert**: WaterQualityAlert uses `message` field (text). Observers will pass message as description to emails.
- **No title/description in ScheduledCut**: ScheduledCut uses `motif` field for description. Observers will pass motif as description. Title will be generic: "Coupure programmée".
- **Type matching**: Observers match type_alerte='coupure' with alert type 'coupure'; type_alerte='restriction' with alert type 'restriction'; type_alerte='qualite' with alert type 'qualite'; type_alerte='tous' matches all.
- **Duplicate prevention**: alert_logs prevents sending same notification twice to same user for same alert.
- **Queue driver**: Database queue means jobs stored in `jobs` table, executed by `php artisan queue:listen` or `queue:work`.
- **Mail logging**: In dev with MAIL_MAILER=log, emails written to storage/logs/laravel.log; verify by checking that file after test.
