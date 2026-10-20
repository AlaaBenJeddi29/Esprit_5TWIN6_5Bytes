<?php

namespace App\Providers;

use App\Models\AlertSubscription;
use App\Models\Drought\Restriction;
use App\Models\Drought\ScheduledCut;
use App\Models\Incident\Incident;
use App\Models\Incident\IncidentFeedback;
use App\Models\Project\Funding;
use App\Models\Project\Project;
use App\Models\Project\ProjectDocument;
use App\Models\Project\ProjectPhase;
use App\Models\Quality\WaterQualityAlert;
use App\Observers\AlertSubscriptionObserver;
use App\Observers\GestionAuditObserver;
use App\Observers\RestrictionObserver;
use App\Observers\ScheduledCutObserver;
use App\Observers\WaterQualityAlertObserver;
use App\Policies\IncidentFeedbackPolicy;
use App\Policies\IncidentPolicy;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailNotification;
use Illuminate\Notifications\Messages\MailMessage;
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
        Gate::policy(IncidentFeedback::class, IncidentFeedbackPolicy::class);

        // Piste d'audit Gestion 5 (insertion seule, acteur côté serveur).
        $audit = GestionAuditObserver::class;
        Project::observe($audit);
        ProjectPhase::observe($audit);
        Funding::observe($audit);
        ProjectDocument::observe($audit);
        // Register observers for alert notifications
        AlertSubscription::observe(AlertSubscriptionObserver::class);
        WaterQualityAlert::observe(WaterQualityAlertObserver::class);
        Restriction::observe(RestrictionObserver::class);
        ScheduledCut::observe(ScheduledCutObserver::class);

        // E-mails d'authentification en français. Les URL sont générées par
        // les mécanismes natifs (APP_URL + route signée), jamais codées en dur.
        ResetPasswordNotification::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], absolute: false));

            return (new MailMessage)
                ->subject('Réinitialisation de votre mot de passe — '.config('app.name'))
                ->greeting('Bonjour '.$notifiable->name.',')
                ->line('Vous recevez cet e-mail car une réinitialisation de mot de passe a été demandée pour votre compte AquaSecure.')
                ->action('Réinitialiser mon mot de passe', $url)
                ->line('Ce lien expirera dans '.config('auth.passwords.users.expire', 60).' minutes.')
                ->line('Si vous n’êtes pas à l’origine de cette demande, aucune action n’est requise.');
        });

        VerifyEmailNotification::toMailUsing(function (object $notifiable, string $url): MailMessage {
            return (new MailMessage)
                ->subject('Vérifiez votre adresse e-mail — '.config('app.name'))
                ->greeting('Bonjour '.$notifiable->name.',')
                ->line('Merci pour votre inscription sur AquaSecure.')
                ->line('Cliquez sur le bouton ci-dessous pour vérifier votre adresse e-mail et accéder à votre espace.')
                ->action('Vérifier mon adresse e-mail', $url)
                ->line('Ce lien est valable '.config('auth.verification.expire', 60).' minutes.')
                ->line('Si vous n’avez pas créé de compte, aucune action n’est requise.');
        });
    }
}
