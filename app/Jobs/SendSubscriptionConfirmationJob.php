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
        // Load the subscription with related user and zone
        $subscription = AlertSubscription::with('user', 'zone')->findOrFail($this->subscriptionId);

        // Send the subscription confirmation email
        Mail::to($subscription->user->email)->send(new SubscriptionConfirmationMail($subscription));
    }
}
