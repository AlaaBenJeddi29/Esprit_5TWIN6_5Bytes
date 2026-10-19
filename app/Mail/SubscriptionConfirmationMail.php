<?php

namespace App\Mail;

use App\Models\AlertSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionConfirmationMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public AlertSubscription $subscription,
    ) {
        $this->subscription->load('user', 'zone');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmation de votre abonnement aux alertes',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-confirmation',
            with: [
                'subscription' => $this->subscription,
                'userName' => $this->subscription->user->name,
                'zoneName' => $this->subscription->zone->nom,
                'typeAlerte' => $this->subscription->typeLabel(),
            ],
        );
    }
}
