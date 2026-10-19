<?php

namespace App\Http\Middleware;

use App\Services\Monitoring\HttpMetricsStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CollectHttpMetrics
{
    public function __construct(private readonly HttpMetricsStore $store)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('metrics')) {
            return $next($request);
        }

        $start = hrtime(true);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->record($request, 500, $start);

            throw $exception;
        }

        $this->record($request, $response->getStatusCode(), $start);

        return $response;
    }

    private function record(Request $request, int $status, int $start): void
    {
        try {
            $this->store->record(
                $request->getMethod(),
                $this->routeLabel($request),
                $status,
                (hrtime(true) - $start) / 1e9,
            );
        } catch (Throwable) {
            // Monitoring failures must never affect the application response.
        }
    }

    private function routeLabel(Request $request): string
    {
        $route = $request->route();

        return $route?->getName()
            ?? $route?->uri()
            ?? 'unmatched';
    }
}
