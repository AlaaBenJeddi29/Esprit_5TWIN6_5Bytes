<?php

namespace Tests\Feature\Back;

use App\Mail\RapportGestionMailable;
use App\Models\User;
use App\Services\GestionReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class OnlineReportEmailRecipientsTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_is_emailed_only_to_the_active_user_who_requested_it(): void
    {
        config(['services.report.mail_to' => 'configured-extra@example.test']);

        $requestingManager = User::factory()->gestionnaire()->create();
        $otherAdmin = User::factory()->admin()->create();
        $inactiveAdmin = User::factory()->admin()->create(['is_active' => false]);

        Mail::fake();

        $service = Mockery::mock(GestionReportService::class);
        $service->shouldReceive('demarrerOllamaSiNecessaire')->once();
        $service->shouldReceive('donnees')->once()->andReturn([]);
        $service->shouldReceive('texteIA')->once()->with([])->andReturn([
            'texte' => 'Rapport de test',
            'source' => 'test',
        ]);
        $this->app->instance(GestionReportService::class, $service);

        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->with('a4')->andReturnSelf();
        $pdf->shouldReceive('output')->once()->andReturn('contenu-pdf');
        Pdf::shouldReceive('loadView')->once()->andReturn($pdf);

        $this->actingAs($requestingManager)
            ->get(route('back.zones.rapport-global'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="rapport-gestion-1-'.now()->format('Y-m-d').'.pdf"')
            ->assertContent('contenu-pdf');

        Mail::assertSentCount(1);
        Mail::assertSent(RapportGestionMailable::class, function (RapportGestionMailable $mail) use (
            $requestingManager,
            $otherAdmin,
            $inactiveAdmin,
        ): bool {
            return $mail->hasTo($requestingManager->email)
                && ! $mail->hasTo($otherAdmin->email)
                && ! $mail->hasTo($inactiveAdmin->email)
                && ! $mail->hasTo('configured-extra@example.test');
        });
    }
}
