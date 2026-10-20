<?php

namespace Tests\Feature\Back;

use App\Jobs\SendAlertNotificationJob;
use App\Jobs\SendSubscriptionConfirmationJob;
use App\Mail\AlertNotificationMail;
use App\Mail\SubscriptionConfirmationMail;
use App\Models\AlertSubscription;
use App\Models\Drought\Restriction;
use App\Models\Drought\ScheduledCut;
use App\Models\Infrastructure\Zone;
use App\Models\Quality\WaterQualityAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AlertNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_subscription_dispatches_the_confirmation_job(): void
    {
        Queue::fake();

        $subscription = AlertSubscription::factory()->create();

        Queue::assertPushed(
            SendSubscriptionConfirmationJob::class,
            fn (SendSubscriptionConfirmationJob $job): bool => $job->subscriptionId === $subscription->id
        );
    }

    public function test_confirmation_job_sends_the_expected_mail(): void
    {
        Queue::fake();
        Mail::fake();

        $user = User::factory()->citoyen()->create(['name' => 'Amina']);
        $zone = Zone::factory()->create(['nom' => 'Zone Nord']);
        $subscription = AlertSubscription::factory()->create([
            'user_id' => $user->id,
            'zone_id' => $zone->id,
            'type_alerte' => 'qualite',
        ]);

        (new SendSubscriptionConfirmationJob($subscription->id))->handle();

        Mail::assertSent(SubscriptionConfirmationMail::class, function (SubscriptionConfirmationMail $mail) use ($user, $subscription): bool {
            return $mail->hasTo($user->email)
                && $mail->subscription->is($subscription)
                && $mail->envelope()->subject === 'Confirmation de votre abonnement aux alertes';
        });
    }

    public function test_confirmation_job_is_skipped_when_subscription_no_longer_exists(): void
    {
        Mail::fake();

        (new SendSubscriptionConfirmationJob(PHP_INT_MAX))->handle();

        Mail::assertNothingSent();
    }

    public function test_creating_quality_drought_alerts_dispatches_the_correct_notifications(): void
    {
        Queue::fake();

        $zone = Zone::factory()->create();
        $alert = WaterQualityAlert::factory()->create(['zone_id' => $zone->id]);
        $restriction = Restriction::factory()->create([
            'zone_id' => $zone->id,
            'titre' => 'Restriction estivale',
        ]);
        $cut = ScheduledCut::factory()->create([
            'zone_id' => $zone->id,
            'motif' => 'Réparation de conduite',
        ]);

        Queue::assertPushed(SendAlertNotificationJob::class, function (SendAlertNotificationJob $job) use ($alert, $zone): bool {
            return $job->modelClass === WaterQualityAlert::class
                && $job->modelId === $alert->id
                && $job->alertType === 'qualite'
                && $job->zoneId === $zone->id
                && str_contains($job->titre, 'Qualité de l\'eau');
        });

        Queue::assertPushed(SendAlertNotificationJob::class, function (SendAlertNotificationJob $job) use ($restriction, $zone): bool {
            return $job->modelClass === Restriction::class
                && $job->modelId === $restriction->id
                && $job->alertType === 'restriction'
                && $job->zoneId === $zone->id
                && $job->titre === 'Restriction estivale';
        });

        Queue::assertPushed(SendAlertNotificationJob::class, function (SendAlertNotificationJob $job) use ($cut, $zone): bool {
            return $job->modelClass === ScheduledCut::class
                && $job->modelId === $cut->id
                && $job->alertType === 'coupure'
                && $job->zoneId === $zone->id
                && $job->titre === 'Coupure programmée';
        });
    }

    public function test_alert_job_notifies_only_matching_active_subscriptions_and_deduplicates(): void
    {
        Queue::fake();
        Mail::fake();

        $zone = Zone::factory()->create(['nom' => 'Zone Centrale']);
        $otherZone = Zone::factory()->create();

        $allTypes = User::factory()->citoyen()->create();
        $qualityOnly = User::factory()->citoyen()->create();
        $inactive = User::factory()->citoyen()->create();
        $otherZoneUser = User::factory()->citoyen()->create();
        $restrictionOnly = User::factory()->citoyen()->create();

        AlertSubscription::factory()->create([
            'user_id' => $allTypes->id,
            'zone_id' => $zone->id,
            'type_alerte' => 'tous',
        ]);
        AlertSubscription::factory()->create([
            'user_id' => $qualityOnly->id,
            'zone_id' => $zone->id,
            'type_alerte' => 'qualite',
        ]);
        AlertSubscription::factory()->create([
            'user_id' => $inactive->id,
            'zone_id' => $zone->id,
            'type_alerte' => 'qualite',
            'actif' => false,
        ]);
        AlertSubscription::factory()->create([
            'user_id' => $otherZoneUser->id,
            'zone_id' => $otherZone->id,
            'type_alerte' => 'qualite',
        ]);
        AlertSubscription::factory()->create([
            'user_id' => $restrictionOnly->id,
            'zone_id' => $zone->id,
            'type_alerte' => 'restriction',
        ]);

        $job = new SendAlertNotificationJob(
            modelClass: WaterQualityAlert::class,
            modelId: 4242,
            alertType: 'qualite',
            titre: 'Alerte Qualité — turbidité',
            description: 'La turbidité dépasse le seuil autorisé.',
            zoneId: $zone->id,
        );

        $job->handle();

        Mail::assertSentCount(2);
        Mail::assertSent(AlertNotificationMail::class, fn (AlertNotificationMail $mail): bool => $mail->hasTo($allTypes->email));
        Mail::assertSent(AlertNotificationMail::class, function (AlertNotificationMail $mail) use ($qualityOnly): bool {
            return $mail->hasTo($qualityOnly->email)
                && $mail->envelope()->subject === "Alerte - Qualité de l'eau"
                && $mail->alertData['zone_name'] === 'Zone Centrale';
        });
        Mail::assertNotSent(AlertNotificationMail::class, fn (AlertNotificationMail $mail): bool => $mail->hasTo($inactive->email));
        Mail::assertNotSent(AlertNotificationMail::class, fn (AlertNotificationMail $mail): bool => $mail->hasTo($otherZoneUser->email));
        Mail::assertNotSent(AlertNotificationMail::class, fn (AlertNotificationMail $mail): bool => $mail->hasTo($restrictionOnly->email));

        $this->assertDatabaseCount('alert_logs', 2);
        $this->assertDatabaseHas('alert_logs', [
            'user_id' => $allTypes->id,
            'zone_id' => $zone->id,
            'type_alerte' => 'qualite',
            'alert_id' => 4242,
        ]);
        $this->assertDatabaseHas('alert_logs', [
            'user_id' => $qualityOnly->id,
            'zone_id' => $zone->id,
            'type_alerte' => 'qualite',
            'alert_id' => 4242,
        ]);

        $job->handle();

        Mail::assertSentCount(2);
        $this->assertDatabaseCount('alert_logs', 2);
    }

    public function test_alert_job_with_an_unknown_type_notifies_only_subscribers_to_all_alerts(): void
    {
        Queue::fake();
        Mail::fake();

        $zone = Zone::factory()->create();
        $allTypes = User::factory()->citoyen()->create();
        $qualityOnly = User::factory()->citoyen()->create();

        AlertSubscription::factory()->create([
            'user_id' => $allTypes->id,
            'zone_id' => $zone->id,
            'type_alerte' => 'tous',
        ]);
        AlertSubscription::factory()->create([
            'user_id' => $qualityOnly->id,
            'zone_id' => $zone->id,
            'type_alerte' => 'qualite',
        ]);

        (new SendAlertNotificationJob(
            modelClass: WaterQualityAlert::class,
            modelId: 7,
            alertType: 'inconnu',
            titre: 'Notification diverse',
            description: 'Information générale.',
            zoneId: $zone->id,
        ))->handle();

        Mail::assertSentCount(1);
        Mail::assertSent(AlertNotificationMail::class, fn (AlertNotificationMail $mail): bool => $mail->hasTo($allTypes->email));
        Mail::assertNotSent(AlertNotificationMail::class, fn (AlertNotificationMail $mail): bool => $mail->hasTo($qualityOnly->email));
        $this->assertDatabaseCount('alert_logs', 1);
    }
}
