<?php

namespace App\Observers;

use App\Jobs\SendSubscriptionConfirmationJob;
use App\Models\AlertSubscription;

class AlertSubscriptionObserver
{
    public function created(AlertSubscription $alertSubscription): void
    {
        SendSubscriptionConfirmationJob::dispatch($alertSubscription->id);
    }
}
