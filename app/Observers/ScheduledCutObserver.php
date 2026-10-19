<?php

namespace App\Observers;

use App\Jobs\SendAlertNotificationJob;
use App\Models\Drought\ScheduledCut;

class ScheduledCutObserver
{
    public function created(ScheduledCut $scheduledCut): void
    {
        SendAlertNotificationJob::dispatch(
            modelClass: ScheduledCut::class,
            modelId: $scheduledCut->id,
            alertType: 'coupure',
            titre: 'Coupure programmée',
            description: $scheduledCut->motif ?? '',
            zoneId: $scheduledCut->zone_id,
        );
    }
}
