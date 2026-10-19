<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Services\Monitoring\HttpMetricsStore;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    public function __invoke(HttpMetricsStore $store): Response
    {
        return response($store->render(), 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }
}
