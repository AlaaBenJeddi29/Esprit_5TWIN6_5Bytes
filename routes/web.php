<?php

use App\Enums\UserRole;
use App\Http\Controllers\Back\AlertSubscriptionController as BackAlertSubscriptionController;
use App\Http\Controllers\Back\AuditEventController;
use App\Http\Controllers\Back\ComplaintController;
use App\Http\Controllers\Back\ConsumptionReadingController;
use App\Http\Controllers\Back\ContractorController;
use App\Http\Controllers\Back\DashboardController;
use App\Http\Controllers\Back\FundingController;
use App\Http\Controllers\Back\GestionnaireController;
use App\Http\Controllers\Back\IncidentController as BackIncidentController;
use App\Http\Controllers\Back\IncidentDashboardController;
use App\Http\Controllers\Back\IncidentMapController;
use App\Http\Controllers\Back\IncidentSimilarityController;
use App\Http\Controllers\Back\InfrastructureController;
use App\Http\Controllers\Back\MaintenanceController;
use App\Http\Controllers\Back\ProjectController;
use App\Http\Controllers\Back\ProjectDocumentController;
use App\Http\Controllers\Back\ProjectPhaseController;
use App\Http\Controllers\Back\QualityAlertController;
use App\Http\Controllers\Back\QualityPlanificationController;
use App\Http\Controllers\Back\QualityThresholdController;
use App\Http\Controllers\Back\QualityZoneAssignmentController;
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
use App\Http\Controllers\Front\IncidentFeedbackController as FrontIncidentFeedbackController;
use App\Http\Controllers\Front\IncidentWritingAssistController as FrontIncidentWritingAssistController;
use App\Http\Controllers\Front\ProjectController as FrontProjectController;
use App\Http\Controllers\Front\PublicDroughtController;
use App\Http\Controllers\Front\PublicInfrastructureController;
use App\Http\Controllers\Front\PublicQualityController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StripeWebhookController;
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

// Webhook Stripe Checkout : hors authentification, vérifié par signature
// (CSRF exempté pour cette route uniquement — voir bootstrap/app.php).
Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

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

        // Aide consultative à la rédaction (aucune écriture en base).
        Route::post('/signaler/aide/questions', [FrontIncidentWritingAssistController::class, 'questions'])
            ->middleware('throttle:8,1')
            ->name('incidents.assist.questions');
        Route::post('/signaler/aide/redaction', [FrontIncidentWritingAssistController::class, 'compose'])
            ->middleware('throttle:8,1')
            ->name('incidents.assist.compose');

        Route::post('/incidents', [FrontIncidentController::class, 'store'])->name('incidents.store');
        Route::get('/mes-incidents/{incident}', [FrontIncidentController::class, 'show'])->name('incidents.show');
        Route::post('/mes-incidents/{incident}/commentaires', [FrontIncidentController::class, 'storeComment'])
            ->name('incidents.comments.store');
        Route::post('/mes-incidents/{incident}/photos', [FrontIncidentController::class, 'storePhoto'])
            ->name('incidents.photos.store');
        Route::get('/mes-incidents/{incident}/photos/{photo}', [FrontIncidentController::class, 'showPhoto'])
            ->name('incidents.photos.show');
        Route::get('/mes-incidents/{incident}/photos/{photo}/telecharger', [FrontIncidentController::class, 'downloadPhoto'])
            ->name('incidents.photos.download');

        // Évaluations et réclamations citoyennes
        Route::get('/mes-evaluations', [FrontIncidentFeedbackController::class, 'index'])
            ->name('feedbacks.index');
        Route::post('/mes-incidents/{incident}/evaluation', [FrontIncidentFeedbackController::class, 'store'])
            ->name('incidents.feedback.store');

        Route::get('/alertes', [AlertSubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::post('/alertes', [AlertSubscriptionController::class, 'store'])->name('subscriptions.store');
        Route::delete('/alertes/{subscription}', [AlertSubscriptionController::class, 'destroy'])
            ->name('subscriptions.destroy');

        Route::post('/projets/{project}/don', [FrontProjectController::class, 'donate'])->name('projects.donate');
        Route::get('/projets/{project}/don/succes', [FrontProjectController::class, 'donationSuccess'])
            ->name('projects.donation.success');
        Route::get('/projets/{project}/don/annule', [FrontProjectController::class, 'donationCancel'])
            ->name('projects.donation.cancel');
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
        // Déclarées AVANT la ressource pour que « dashboard » et « map » ne
        // soient pas capturés par la route incidents.show ({incident}).
        Route::get('incidents/dashboard', [IncidentDashboardController::class, 'index'])
            ->name('incidents.dashboard');
        Route::get('incidents/dashboard/pdf', [IncidentDashboardController::class, 'pdf'])
            ->name('incidents.dashboard.pdf');
        Route::get('incidents/map', [IncidentMapController::class, 'index'])->name('incidents.map');
        Route::get('incidents/map/data', [IncidentMapController::class, 'data'])->name('incidents.map.data');
        Route::resource('incidents', BackIncidentController::class)->names('incidents');
        Route::patch('incidents/{incident}/statut', [BackIncidentController::class, 'updateStatus'])
            ->name('incidents.status');
        Route::post('incidents/{incident}/commentaires', [BackIncidentController::class, 'storeComment'])
            ->name('incidents.comments.store');

        // Incidents similaires : détection à la demande et décision de gestionnaire
        Route::post('incidents/{incident}/similarities/detect', [IncidentSimilarityController::class, 'detect'])
            ->middleware('throttle:6,1')
            ->name('incidents.similarities.detect');
        Route::patch('incidents/{incident}/similarities/{similarity}', [IncidentSimilarityController::class, 'decide'])
            ->middleware('throttle:30,1')
            ->name('incidents.similarities.decide');

        Route::get('incidents/{incident}/photos/{photo}', [BackIncidentController::class, 'showPhoto'])
            ->name('incidents.photos.show');
        Route::get('incidents/{incident}/photos/{photo}/telecharger', [BackIncidentController::class, 'downloadPhoto'])
            ->name('incidents.photos.download');

        // Réclamations et satisfaction citoyenne
        // « stats » est déclarée AVANT {feedback} pour ne pas être capturée
        // par la route de détail.
        Route::get('complaints/stats', [ComplaintController::class, 'stats'])->name('complaints.stats');
        Route::get('complaints', [ComplaintController::class, 'index'])->name('complaints.index');
        Route::get('complaints/{feedback}', [ComplaintController::class, 'show'])->name('complaints.show');
        Route::patch('complaints/{feedback}/review', [ComplaintController::class, 'review'])
            ->name('complaints.review');
        Route::post('complaints/{feedback}/response', [ComplaintController::class, 'respond'])
            ->name('complaints.response');
        Route::patch('complaints/{feedback}/decision', [ComplaintController::class, 'decide'])
            ->name('complaints.decision');
        Route::patch('complaints/{feedback}/close', [ComplaintController::class, 'close'])
            ->name('complaints.close');
        Route::patch('complaints/{feedback}/reopen', [ComplaintController::class, 'reopen'])
            ->name('complaints.reopen');

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

        // Planification des prélèvements — route calendrier déclarée AVANT la
        // ressource pour ne pas être capturée par {planification}.
        Route::get('quality-planifications/calendrier', [QualityPlanificationController::class, 'calendar'])
            ->name('quality-planifications.calendar');
        Route::resource('quality-planifications', QualityPlanificationController::class)
            ->names('quality-planifications')
            ->except(['show'])
            ->parameters(['quality-planifications' => 'planification']);
        Route::get('quality-zone-assignments', [QualityZoneAssignmentController::class, 'index'])
            ->name('quality-assignments.index');
        Route::put('quality-zone-assignments', [QualityZoneAssignmentController::class, 'update'])
            ->name('quality-assignments.update');

        // Gestion 4 — Sécheresse, consommation et continuité de service
        Route::get('water-levels/export/{format}', [WaterLevelController::class, 'export'])
            ->whereIn('format', ['pdf', 'excel', 'csv'])
            ->name('water-levels.export');
        Route::resource('water-levels', WaterLevelController::class)
            ->names('water-levels')
            ->except(['show'])
            ->parameters(['water-levels' => 'level']);

        Route::get('consumption-readings/export/{format}', [ConsumptionReadingController::class, 'export'])
            ->whereIn('format', ['pdf', 'excel', 'csv'])
            ->name('consumption-readings.export');
        Route::get('consumption-readings/previsions', [ConsumptionReadingController::class, 'forecast'])
            ->name('consumption-readings.forecast');
        Route::resource('consumption-readings', ConsumptionReadingController::class)
            ->names('consumption-readings')
            ->except(['show'])
            ->parameters(['consumption-readings' => 'reading']);

        Route::get('restrictions/export/{format}', [RestrictionController::class, 'export'])
            ->whereIn('format', ['pdf', 'excel', 'csv'])
            ->name('restrictions.export');
        Route::resource('restrictions', RestrictionController::class)
            ->names('restrictions')
            ->except(['show']);

        Route::get('scheduled-cuts/export/{format}', [ScheduledCutController::class, 'export'])
            ->whereIn('format', ['pdf', 'excel', 'csv'])
            ->name('scheduled-cuts.export');
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
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])
        ->middleware('throttle:10,1')
        ->name('profile.photo.update');
    Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
