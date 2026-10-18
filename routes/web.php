<?php

use App\Enums\UserRole;
use App\Http\Controllers\Back\AlertSubscriptionController as BackAlertSubscriptionController;
use App\Http\Controllers\Back\AuditEventController;
use App\Http\Controllers\Back\ConsumptionReadingController;
use App\Http\Controllers\Back\ContractorController;
use App\Http\Controllers\Back\DashboardController;
use App\Http\Controllers\Back\FundingController;
use App\Http\Controllers\Back\GestionnaireController;
use App\Http\Controllers\Back\IncidentController as BackIncidentController;
use App\Http\Controllers\Back\InfrastructureController;
use App\Http\Controllers\Back\MaintenanceController;
use App\Http\Controllers\Back\ProjectController;
use App\Http\Controllers\Back\ProjectDocumentController;
use App\Http\Controllers\Back\ProjectPhaseController;
use App\Http\Controllers\Back\QualityAlertController;
use App\Http\Controllers\Back\QualityThresholdController;
use App\Http\Controllers\Back\ReportController;
use App\Http\Controllers\Back\RestrictionController;
use App\Http\Controllers\Back\ScheduledCutController;
use App\Http\Controllers\Back\WaterLevelController;
use App\Http\Controllers\Back\WaterQualitySampleController;
use App\Http\Controllers\Back\ZoneController;
use App\Http\Controllers\Front\AlertSubscriptionController;
use App\Http\Controllers\Front\CitizenDashboardController;
use App\Http\Controllers\Front\HomeController;
use App\Http\Controllers\Front\IncidentController as FrontIncidentController;
use App\Http\Controllers\Front\ProjectController as FrontProjectController;
use App\Http\Controllers\Front\PublicDroughtController;
use App\Http\Controllers\Front\PublicInfrastructureController;
use App\Http\Controllers\Front\PublicQualityController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Front Office public
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('front.home');

// Gestion 3 — Qualité de l'eau (données publiques)
Route::get('/qualite', [PublicQualityController::class, 'index'])->name('public.quality.index');
Route::get('/qualite/{zone}', [PublicQualityController::class, 'zone'])->name('public.quality.zone');

// Gestion 4 — Sécheresse & continuité de service (données publiques)
Route::get('/secheresse', [PublicDroughtController::class, 'index'])->name('public.drought.index');
Route::get('/secheresse/{zone}', [PublicDroughtController::class, 'zone'])->name('public.drought.zone');

// Gestion 1 — Cartographie publique des infrastructures
Route::get('/infrastructures', [PublicInfrastructureController::class, 'index'])->name('public.infrastructures.index');
Route::get('/infrastructures/{infrastructure}', [PublicInfrastructureController::class, 'show'])->name('public.infrastructures.show');

// Gestion 5 — Projets, financement participatif simulé et assistant projet
Route::get('/projets', [FrontProjectController::class, 'index'])->name('public.projects.index');
Route::get('/projets/{project}', [FrontProjectController::class, 'show'])->name('public.projects.show');
Route::get('/projets/{project}/documents/{document}/telecharger', [FrontProjectController::class, 'downloadDocument'])
    ->name('public.projects.documents.download');
Route::post('/projets/{project}/assistant', [FrontProjectController::class, 'assistant'])
    ->middleware('throttle:15,1')
    ->name('public.projects.assistant');

// Suivi public d'un incident par référence
Route::get('/suivi', [FrontIncidentController::class, 'trackForm'])->name('front.incidents.track.form');
Route::post('/suivi', [FrontIncidentController::class, 'track'])->name('front.incidents.track');

/*
|--------------------------------------------------------------------------
| Dispatcher /dashboard — redirection selon le rôle (lu côté serveur)
|--------------------------------------------------------------------------
| Conserve le nom Breeze `dashboard` (cible de redirect()->intended()).
*/
Route::get('/dashboard', function (Request $request) {
    return match ($request->user()->role) {
        UserRole::Admin, UserRole::Gestionnaire => redirect()->route('back.dashboard'),
        default => redirect()->route('front.home'),
    };
})->middleware(['auth', 'verified'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Espace citoyen (Front Office authentifié)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:citoyen'])
    ->name('citizen.')
    ->group(function () {
        Route::get('/mon-espace', [CitizenDashboardController::class, 'index'])->name('dashboard');

        Route::get('/mes-incidents', [FrontIncidentController::class, 'index'])->name('incidents.index');
        Route::get('/signaler', [FrontIncidentController::class, 'create'])->name('incidents.create');
        Route::post('/incidents', [FrontIncidentController::class, 'store'])->name('incidents.store');
        Route::get('/mes-incidents/{incident}', [FrontIncidentController::class, 'show'])->name('incidents.show');
        Route::post('/mes-incidents/{incident}/commentaires', [FrontIncidentController::class, 'storeComment'])
            ->name('incidents.comments.store');
        Route::post('/mes-incidents/{incident}/photos', [FrontIncidentController::class, 'storePhoto'])
            ->name('incidents.photos.store');
        Route::get('/mes-incidents/{incident}/photos/{photo}/telecharger', [FrontIncidentController::class, 'downloadPhoto'])
            ->name('incidents.photos.download');

        Route::get('/alertes', [AlertSubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::post('/alertes', [AlertSubscriptionController::class, 'store'])->name('subscriptions.store');
        Route::delete('/alertes/{subscription}', [AlertSubscriptionController::class, 'destroy'])
            ->name('subscriptions.destroy');

        Route::post('/projets/{project}/don', [FrontProjectController::class, 'donate'])->name('projects.donate');
    });

/*
|--------------------------------------------------------------------------
| Back Office (gestionnaires + administrateurs)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:gestionnaire,admin'])
    ->prefix('back')
    ->name('back.')
    ->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        // Signalements (fonctionnalité existante — conservée)
        Route::resource('reports', ReportController::class)
            ->names('reports')
            ->except(['destroy']);

        // Gestion 1 — Infrastructures (zones, infrastructures, maintenances)
        // Rapport global PDF — déclaré AVANT la ressource pour ne pas être
        // capturé par la route zones.show ({zone}).
        Route::get('zones/rapport-global', [ZoneController::class, 'rapportGlobal'])->name('zones.rapport-global');
        Route::resource('zones', ZoneController::class)->names('zones');
        Route::resource('infrastructures', InfrastructureController::class)->names('infrastructures');
        Route::post('infrastructures/{infrastructure}/score-risque', [InfrastructureController::class, 'refreshRiskScore'])
            ->name('infrastructures.risk-score');
        Route::resource('maintenances', MaintenanceController::class)->names('maintenances');

        // Gestion 2 — Incidents
        Route::resource('incidents', BackIncidentController::class)->names('incidents');
        Route::patch('incidents/{incident}/statut', [BackIncidentController::class, 'updateStatus'])
            ->name('incidents.status');
        Route::post('incidents/{incident}/commentaires', [BackIncidentController::class, 'storeComment'])
            ->name('incidents.comments.store');
        Route::get('incidents/{incident}/photos/{photo}/telecharger', [BackIncidentController::class, 'downloadPhoto'])
            ->name('incidents.photos.download');

        // Gestion 3 — Qualité de l'eau
        Route::resource('quality-thresholds', QualityThresholdController::class)
            ->names('quality-thresholds')
            ->except(['show'])
            ->parameters(['quality-thresholds' => 'threshold']);
        Route::resource('quality-samples', WaterQualitySampleController::class)
            ->names('quality-samples')
            ->parameters(['quality-samples' => 'sample']);
        Route::resource('quality-alerts', QualityAlertController::class)
            ->names('quality-alerts')
            ->only(['index', 'update', 'destroy'])
            ->parameters(['quality-alerts' => 'alert']);

        // Gestion 4 — Sécheresse, consommation et continuité de service
        Route::resource('water-levels', WaterLevelController::class)
            ->names('water-levels')
            ->except(['show'])
            ->parameters(['water-levels' => 'level']);
        Route::get('consumption-readings/previsions', [ConsumptionReadingController::class, 'forecast'])
            ->name('consumption-readings.forecast');
        Route::resource('consumption-readings', ConsumptionReadingController::class)
            ->names('consumption-readings')
            ->except(['show'])
            ->parameters(['consumption-readings' => 'reading']);
        Route::resource('restrictions', RestrictionController::class)
            ->names('restrictions')
            ->except(['show']);
        Route::resource('scheduled-cuts', ScheduledCutController::class)
            ->names('scheduled-cuts')
            ->except(['show'])
            ->parameters(['scheduled-cuts' => 'cut']);
        Route::get('alert-subscriptions', [BackAlertSubscriptionController::class, 'index'])
            ->name('alert-subscriptions.index');

        // Gestion 5 — Projets & financement (entreprises, projets, phases, financements, documents)
        Route::resource('contractors', ContractorController::class)
            ->names('contractors')
            ->parameters(['contractors' => 'contractor']);

        Route::resource('projects', ProjectController::class)->names('projects');

        Route::prefix('projects/{project}')->name('projects.')->group(function () {
            // Rapport IA du projet (lecture seule — PDF Dompdf, Gestion 5).
            Route::get('rapport', [ProjectController::class, 'rapport'])->name('rapport');

            Route::resource('phases', ProjectPhaseController::class)
                ->names('phases')
                ->except(['index', 'show'])
                ->parameters(['phases' => 'phase']);

            Route::resource('fundings', FundingController::class)
                ->names('fundings')
                ->except(['index', 'show'])
                ->parameters(['fundings' => 'funding']);

            Route::resource('documents', ProjectDocumentController::class)
                ->names('documents')
                ->except(['index', 'show'])
                ->parameters(['documents' => 'document']);
        });

        // Journal d'audit Gestion 5 (lecture seule — insertion seule par construction).
        Route::get('audit-events', [AuditEventController::class, 'index'])->name('audit-events.index');

        // Gestion des gestionnaires : réservée aux administrateurs (Lot 1b)
        Route::middleware('role:admin')->group(function () {
            Route::resource('gestionnaires', GestionnaireController::class)
                ->names('gestionnaires')
                ->except(['show', 'destroy'])
                ->parameters(['gestionnaires' => 'gestionnaire']);

            Route::patch('gestionnaires/{gestionnaire}/toggle', [GestionnaireController::class, 'toggle'])
                ->name('gestionnaires.toggle');
        });
    });

/*
|--------------------------------------------------------------------------
| Profil (utilisateurs authentifiés)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
