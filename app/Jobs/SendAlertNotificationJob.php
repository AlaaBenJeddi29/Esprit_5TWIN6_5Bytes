<?php

namespace App\Jobs;

use App\Mail\AlertNotificationMail;
use App\Models\AlertLog;
use App\Models\AlertSubscription;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendAlertNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $modelClass,
        public int $modelId,
        public string $alertType,
        public string $titre,
        public string $description,
        public int $zoneId,
    ) {
        $this->queue = 'default';
    }

    public function handle(): void
    {
        // Get the zone
        $zone = Zone::findOrFail($this->zoneId);

        // Map model class to alert subscription type
        $subscriptionTypes = match ($this->alertType) {
            'qualite' => ['tous', 'qualite'],
            'restriction' => ['tous', 'restriction'],
            'coupure' => ['tous', 'coupure'],
            default => ['tous'],
        };

        // Find all active subscriptions for this zone that match the alert type
        $subscriptions = AlertSubscription::where('zone_id', $this->zoneId)
            ->where('actif', true)
            ->whereIn('type_alerte', $subscriptionTypes)
            ->with('user')
            ->get();

        foreach ($subscriptions as $subscription) {
            // Check if we already sent this notification to this user for this specific alert
            $alreadySent = AlertLog::where('user_id', $subscription->user_id)
                ->where('zone_id', $this->zoneId)
                ->where('type_alerte', $this->alertType)
                ->where('alert_id', $this->modelId)
                ->exists();

            if ($alreadySent) {
                continue; // Skip duplicate sends
            }

            // Send the notification email
            $alertData = [
                'type_alerte' => $this->alertType,
                'titre' => $this->titre,
                'description' => $this->description,
                'zone_name' => $zone->nom,
                'created_at' => now(),
            ];

            Mail::to($subscription->user->email)->send(new AlertNotificationMail($alertData));

            // Log the sent notification
            AlertLog::create([
                'user_id' => $subscription->user_id,
                'zone_id' => $this->zoneId,
                'type_alerte' => $this->alertType,
                'alert_id' => $this->modelId,
                'titre' => $this->titre,
                'description' => $this->description,
                'sent_at' => now(),
            ]);
        }
    }
}
