<?php

namespace App\Jobs;

use App\Mail\SubscriptionConfirmationMail;
use App\Models\AlertSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $subscriptionId,
    ) {
        $this->queue = 'default';
    }

    public function handle(): void
    {
        // L'abonnement a pu être supprimé avant le traitement du job :
        // il n'y a alors plus rien à envoyer.
        $subscription = AlertSubscription::with('user', 'zone')->find($this->subscriptionId);

        if ($subscription === null) {
            return;
        }

        Mail::to($subscription->user->email)->send(new SubscriptionConfirmationMail($subscription));
    }
}
