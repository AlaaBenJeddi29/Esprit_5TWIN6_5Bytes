<?php

namespace App\Providers;

use App\Models\Incident\Incident;
use App\Models\Project\Funding;
use App\Models\Project\Project;
use App\Models\Project\ProjectDocument;
use App\Models\Project\ProjectPhase;
use App\Observers\GestionAuditObserver;
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

        // Piste d'audit Gestion 5 (insertion seule, acteur côté serveur).
        $audit = GestionAuditObserver::class;
        Project::observe($audit);
        ProjectPhase::observe($audit);
        Funding::observe($audit);
        ProjectDocument::observe($audit);
    }
}
