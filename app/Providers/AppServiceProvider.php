<?php

namespace App\Providers;

use App\Models\AlertSubscription;
use App\Models\Drought\Restriction;
use App\Models\Drought\ScheduledCut;
use App\Models\Incident\Incident;
use App\Models\Quality\WaterQualityAlert;
use App\Observers\AlertSubscriptionObserver;
use App\Observers\RestrictionObserver;
use App\Observers\ScheduledCutObserver;
use App\Observers\WaterQualityAlertObserver;
use App\Policies\IncidentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Incident::class, IncidentPolicy::class);

        // Register observers for alert notifications
        AlertSubscription::observe(AlertSubscriptionObserver::class);
        WaterQualityAlert::observe(WaterQualityAlertObserver::class);
        Restriction::observe(RestrictionObserver::class);
        ScheduledCut::observe(ScheduledCutObserver::class);
    }
}
