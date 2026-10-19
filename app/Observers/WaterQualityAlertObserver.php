<?php

namespace App\Observers;

use App\Jobs\SendAlertNotificationJob;
use App\Models\Quality\WaterQualityAlert;

class WaterQualityAlertObserver
{
    public function created(WaterQualityAlert $alert): void
    {
        SendAlertNotificationJob::dispatch(
            modelClass: WaterQualityAlert::class,
            modelId: $alert->id,
            alertType: 'qualite',
            titre: 'Alerte Qualité de l\'eau - ' . $alert->parametre,
            description: $alert->message,
            zoneId: $alert->zone_id,
        );
    }
}
