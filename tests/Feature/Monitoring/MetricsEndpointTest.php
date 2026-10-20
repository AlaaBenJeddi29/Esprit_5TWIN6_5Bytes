<?php

namespace Tests\Feature\Monitoring;

use App\Services\Monitoring\HttpMetricsStore;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MetricsEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (new HttpMetricsStore())->reset();
    }

    public function test_metrics_endpoint_exposes_request_counters_and_latency_histogram(): void
    {
        Route::get('/_monitoring-probe', fn () => response('ok', 201))->name('monitoring.probe');

        $this->get('/_monitoring-probe')->assertStatus(201);

        $response = $this->get('/metrics');

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));

        $body = (string) $response->getContent();

        $this->assertStringContainsString(
            'aquasecure_http_requests_total{method="GET",route="monitoring.probe",status="201"} 1',
            $body,
        );
        $this->assertStringContainsString(
            'aquasecure_http_request_duration_seconds_bucket{method="GET",route="monitoring.probe",le="+Inf"} 1',
            $body,
        );
        $this->assertStringContainsString(
            'aquasecure_http_request_duration_seconds_count{method="GET",route="monitoring.probe"} 1',
            $body,
        );
        $this->assertStringContainsString('aquasecure_application_info{', $body);
    }

    public function test_unmatched_requests_are_grouped_under_a_single_label(): void
    {
        $this->get('/definitely-missing-page')->assertNotFound();

        $body = (string) $this->get('/metrics')->getContent();

        $this->assertStringContainsString(
            'aquasecure_http_requests_total{method="GET",route="unmatched",status="404"} 1',
            $body,
        );
    }
}
