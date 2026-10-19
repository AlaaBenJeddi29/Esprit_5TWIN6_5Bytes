<?php

namespace App\Observers;

use App\Jobs\SendAlertNotificationJob;
use App\Models\Drought\Restriction;

class RestrictionObserver
{
    public function created(Restriction $restriction): void
    {
        SendAlertNotificationJob::dispatch(
            modelClass: Restriction::class,
            modelId: $restriction->id,
            alertType: 'restriction',
            titre: $restriction->titre,
            description: $restriction->description ?? '',
            zoneId: $restriction->zone_id,
        );
    }
}
