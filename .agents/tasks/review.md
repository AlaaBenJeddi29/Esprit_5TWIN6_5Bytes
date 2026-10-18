# Alert Subscription Email Notification System

Implements automated email notifications for water alert subscriptions: a confirmation email when a citizen subscribes, followed by alert emails for water quality issues, usage restrictions, and scheduled outages. All components follow the planned architecture, with observers handling model lifecycle events and a database-backed queue managing async delivery.

**Watch for**: The AlertSubscriptionObserver uses Mail::to() instead of dispatching a queued Mailable, which dispatches synchronously and bypasses the queue. Job deduplication relies on checking four fields (user_id, zone_id, type_alerte, titre); updates to alert titles in existing records won't prevent resends. Email links in the alert notification blade should match the navigation structure of the application.

**Verdict**: NEEDS_CHANGES

---

## High-level view

The observer pattern wires model creation events to email triggers: AlertSubscriptionObserver fires on new subscriptions and queues a confirmation email, while WaterQualityAlertObserver, RestrictionObserver, and ScheduledCutObserver fire on their respective alert creations and dispatch a job that finds matching subscribers and queues notifications for each.

All four observers are properly registered in AppServiceProvider.boot(). AlertSubscriptionObserver couples the dispatch decision to the observer by using Mail::to()->queue() rather than dispatching a job, inconsistent with the alert notification flow where observers dispatch SendAlertNotificationJob. This weakens isolation between model lifecycle and queue logic — future changes to queuing strategy (custom queue name, delay, or retries) would require observer changes rather than job changes alone.

The SendAlertNotificationJob prevents duplicate sends by checking (user_id, zone_id, type_alerte, titre). This is brittle: if an alert's titre is edited after creation but before the job runs, subscribers could receive duplicate notifications for the same alert event with different titles. The implementation omits alert_id from the deduplication key, preventing the system from reliably identifying which source alert triggered a send. The plan specified alert_id should be stored in alert_logs, but the migration and model don't include it.

<details>
<summary>Issues (2)</summary>

1. **Subscription confirmation dispatch pattern** — AlertSubscriptionObserver uses Mail::to()->queue() instead of dispatching a queued job, causing sync decision-making in the observer and inconsistent with the alert notification flow.

2. **Alert deduplication relies on títle immutability** — SendAlertNotificationJob checks only (user_id, zone_id, type_alerte, titre) for duplicates. Editing an alert's titre after creation will cause duplicate sends to the same user.

</details>

---

<details>
<summary>Details</summary>

### Subscription Confirmation Dispatch Inconsistency

AlertSubscriptionObserver queues the SubscriptionConfirmationMail directly via `Mail::to()->queue()` rather than dispatching a job. All three alert observers (WaterQuality, Restriction, ScheduledCut) dispatch SendAlertNotificationJob instead. A cleaner approach would dispatch a job from the observer (e.g., SendSubscriptionConfirmationJob) that handles the Mail::queue() call, keeping all queue logic in job classes and allowing queuing strategy changes to be made in one place rather than both the observer and the Mailable.

### Alert Deduplication Gap

SendAlertNotificationJob prevents duplicate sends by checking:
```php
AlertLog::where('user_id', $subscription->user_id)
    ->where('zone_id', $this->zoneId)
    ->where('type_alerte', $this->alertType)
    ->where('titre', $this->titre)
    ->exists()
```

The query matches on titre, which is user-editable data in Restriction and ScheduledCut models. Without alert_id as a deduplication key, the system cannot distinguish between two different alerts with identical titles, nor can it reliably prevent resends if an alert is edited before the job executes. The plan specified that alert_logs should store alert_id (the FK to the source record), but the migration and model omit it entirely.

### Email Views and Variable Passing

The subscription-confirmation blade receives userName, zoneName, and typeAlerte as pre-computed values. The alert-notification blade accesses alertData array fields. The `created_at` field is set to `now()` in the job, not sourced from the alert model, so the "Date de l'alerte" in the email reflects send time, potentially seconds or minutes after the actual alert was created. For audit purposes this is reasonable, but the distinction is worth noting.

### Queue and Mail Configuration

The .env file is configured for production (MAIL_MAILER=smtp with Gmail credentials). QUEUE_CONNECTION is set to database. The .env.example documents both development (MAIL_MAILER=log) and production modes with explanatory comments.

### Migration and Alert Logs Model

The alert_logs migration creates foreign keys with cascadeOnDelete and indexes on (user_id, zone_id) and (type_alerte). The model correctly defines fillable fields, belongs-to relationships, and datetime casting. However, the migration omits the alert_id column specified in the plan, which is the root cause of the deduplication gap.

</details>

---

## File map

| File | Change |
|------|--------|
| app/Observers/AlertSubscriptionObserver.php | New: watches AlertSubscription.created(), queues confirmation mail |
| app/Observers/WaterQualityAlertObserver.php | New: watches WaterQualityAlert.created(), dispatches alert job |
| app/Observers/RestrictionObserver.php | New: watches Restriction.created(), dispatches alert job |
| app/Observers/ScheduledCutObserver.php | New: watches ScheduledCut.created(), dispatches alert job |
| app/Mail/SubscriptionConfirmationMail.php | New: Mailable for subscription confirmation, implements ShouldQueue |
| app/Mail/AlertNotificationMail.php | New: Mailable for alert notifications, implements ShouldQueue |
| app/Jobs/SendAlertNotificationJob.php | New: queued job to send alert notifications and log sends |
| app/Models/AlertLog.php | New: audit model for tracking sent notifications |
| app/Providers/AppServiceProvider.php | Modified: register all four observers in boot() |
| database/migrations/2026_10_18_140828_create_alert_logs_table.php | New: alert_logs table with deduplication indexes |
| resources/views/emails/subscription-confirmation.blade.php | New: French blade template for subscription confirmation |
| resources/views/emails/alert-notification.blade.php | New: French blade template for alert notifications, color-coded by type |
| .env | Modified: added/configured MAIL_* and QUEUE_CONNECTION for production |
| .env.example | Modified: documented mail configuration with dev/prod notes |

Full diff: `git diff` against base branch.
