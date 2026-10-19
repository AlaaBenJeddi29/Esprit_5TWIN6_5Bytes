<?php

namespace App\Services\Monitoring;

final class HttpMetricsStore
{
    /**
     * @var list<float>
     */
    public const BUCKETS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0];

    public function record(string $method, string $route, int $status, float $seconds): void
    {
        $this->mutate(function (array $data) use ($method, $route, $status, $seconds): array {
            $counterKey = $method.'|'.$route.'|'.$status;
            $data['counters'][$counterKey] = ((int) ($data['counters'][$counterKey] ?? 0)) + 1;

            $durationKey = $method.'|'.$route;
            $duration = $data['durations'][$durationKey] ?? ['buckets' => [], 'sum' => 0.0, 'count' => 0];

            foreach (self::BUCKETS as $bucket) {
                if ($seconds <= $bucket) {
                    $label = self::bucketLabel($bucket);
                    $duration['buckets'][$label] = ((int) ($duration['buckets'][$label] ?? 0)) + 1;
                }
            }

            $duration['sum'] = (float) ($duration['sum'] ?? 0) + $seconds;
            $duration['count'] = ((int) ($duration['count'] ?? 0)) + 1;
            $data['durations'][$durationKey] = $duration;

            return $data;
        });
    }

    public function reset(): void
    {
        $this->mutate(fn (array $data): array => ['counters' => [], 'durations' => []]);
    }

    public function render(): string
    {
        $data = $this->read();
        $counters = $data['counters'];
        $durations = $data['durations'];

        ksort($counters);
        ksort($durations);

        $lines = [
            '# HELP aquasecure_http_requests_total Total HTTP requests handled by the application.',
            '# TYPE aquasecure_http_requests_total counter',
        ];

        foreach ($counters as $key => $count) {
            [$method, $route, $status] = array_pad(explode('|', (string) $key, 3), 3, '');

            $lines[] = sprintf(
                'aquasecure_http_requests_total{method="%s",route="%s",status="%s"} %d',
                self::escape($method),
                self::escape($route),
                self::escape($status),
                (int) $count,
            );
        }

        $lines[] = '# HELP aquasecure_http_request_duration_seconds HTTP request duration in seconds.';
        $lines[] = '# TYPE aquasecure_http_request_duration_seconds histogram';

        foreach ($durations as $key => $duration) {
            [$method, $route] = array_pad(explode('|', (string) $key, 2), 2, '');
            $method = self::escape($method);
            $route = self::escape($route);
            $buckets = is_array($duration['buckets'] ?? null) ? $duration['buckets'] : [];
            $count = (int) ($duration['count'] ?? 0);

            foreach (self::BUCKETS as $bucket) {
                $label = self::bucketLabel($bucket);

                $lines[] = sprintf(
                    'aquasecure_http_request_duration_seconds_bucket{method="%s",route="%s",le="%s"} %d',
                    $method,
                    $route,
                    $label,
                    (int) ($buckets[$label] ?? 0),
                );
            }

            $lines[] = sprintf(
                'aquasecure_http_request_duration_seconds_bucket{method="%s",route="%s",le="+Inf"} %d',
                $method,
                $route,
                $count,
            );
            $lines[] = sprintf(
                'aquasecure_http_request_duration_seconds_sum{method="%s",route="%s"} %s',
                $method,
                $route,
                self::number((float) ($duration['sum'] ?? 0)),
            );
            $lines[] = sprintf(
                'aquasecure_http_request_duration_seconds_count{method="%s",route="%s"} %d',
                $method,
                $route,
                $count,
            );
        }

        $lines[] = '# HELP aquasecure_application_info Static information about the application.';
        $lines[] = '# TYPE aquasecure_application_info gauge';
        $lines[] = sprintf(
            'aquasecure_application_info{environment="%s",laravel_version="%s",php_version="%s"} 1',
            self::escape((string) app()->environment()),
            self::escape(app()->version()),
            self::escape(PHP_VERSION),
        );

        return implode("\n", $lines)."\n";
    }

    /**
     * @return array{counters: array<string, int>, durations: array<string, array{buckets: array<string, int>, sum: float, count: int}>}
     */
    private function read(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return ['counters' => [], 'durations' => []];
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return ['counters' => [], 'durations' => []];
        }

        try {
            if (! flock($handle, LOCK_SH)) {
                return ['counters' => [], 'durations' => []];
            }

            $contents = stream_get_contents($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return $this->decode($contents ?: '');
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $callback
     */
    private function mutate(callable $callback): void
    {
        $path = $this->path();
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return;
        }

        $handle = @fopen($path, 'c+b');

        if ($handle === false) {
            return;
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                return;
            }

            $contents = stream_get_contents($handle);
            $data = $callback($this->decode($contents ?: ''));

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}');
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array{counters: array<string, int>, durations: array<string, array{buckets: array<string, int>, sum: float, count: int}>}
     */
    private function decode(string $contents): array
    {
        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            $decoded = [];
        }

        return [
            'counters' => is_array($decoded['counters'] ?? null) ? $decoded['counters'] : [],
            'durations' => is_array($decoded['durations'] ?? null) ? $decoded['durations'] : [],
        ];
    }

    private function path(): string
    {
        return storage_path('app/monitoring/http_metrics.json');
    }

    private static function bucketLabel(float $bucket): string
    {
        return rtrim(rtrim(number_format($bucket, 3, '.', ''), '0'), '.');
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.') ?: '0';
    }

    private static function escape(string $value): string
    {
        return str_replace(['\\', "\n", '"'], ['\\\\', '\\n', '\\"'], $value);
    }
}
