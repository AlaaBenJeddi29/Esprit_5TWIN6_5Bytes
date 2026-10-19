@php
    $formatValue = fn (float $value): string => rtrim(rtrim(number_format($value, 4, ',', ' '), '0'), ',');
@endphp

@if (empty($trends))
    <div class="rounded-md bg-white p-8 text-center shadow-1 dark:bg-dark-2">
        <p class="text-sm text-body-color dark:text-dark-6">Aucune mesure disponible pour tracer l'évolution des paramètres de cette zone.</p>
    </div>
@else
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($trends as $trend)
            @php
                $points = $trend['points'];
                $count = count($points);
                $values = array_column($points, 'valeur');
                $min = (float) min($values);
                $max = (float) max($values);

                if ($min === $max) {
                    $padding = max(abs($min) * 0.1, 1);
                    $min -= $padding;
                    $max += $padding;
                }

                $width = 320;
                $height = 110;
                $padX = 10;
                $padTop = 12;
                $padBottom = 22;
                $innerWidth = $width - (2 * $padX);
                $innerHeight = $height - $padTop - $padBottom;
                $span = $max - $min;

                $coords = [];
                foreach ($points as $index => $point) {
                    $x = $count === 1 ? $width / 2 : $padX + ($index * $innerWidth / ($count - 1));
                    $y = $padTop + (($max - $point['valeur']) / $span) * $innerHeight;
                    $coords[] = ['x' => round($x, 2), 'y' => round($y, 2), 'point' => $point];
                }

                $polyline = implode(' ', array_map(fn (array $coord): string => $coord['x'].','.$coord['y'], $coords));
                $last = $coords[$count - 1];
            @endphp

            <figure class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                <figcaption class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-dark dark:text-white">{{ $trend['label'] }}</p>
                        <p class="mt-0.5 text-xs text-body-color dark:text-dark-6">{{ $count }} mesure{{ $count > 1 ? 's' : '' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-semibold text-dark dark:text-white">
                            {{ $formatValue($last['point']['valeur']) }}
                            <span class="text-xs font-normal text-body-color dark:text-dark-6">{{ $trend['unite'] }}</span>
                        </p>
                        <p class="text-[11px] text-body-secondary">Dernière valeur</p>
                    </div>
                </figcaption>

                <svg viewBox="0 0 {{ $width }} {{ $height }}" class="mt-3 h-auto w-full" role="img" aria-label="Évolution du paramètre {{ $trend['label'] }} en {{ $trend['unite'] }}">
                    <g class="text-stroke dark:text-dark-3" stroke="currentColor" stroke-width="1" stroke-dasharray="3 3">
                        <line x1="{{ $padX }}" y1="{{ $padTop }}" x2="{{ $width - $padX }}" y2="{{ $padTop }}" />
                        <line x1="{{ $padX }}" y1="{{ $padTop + ($innerHeight / 2) }}" x2="{{ $width - $padX }}" y2="{{ $padTop + ($innerHeight / 2) }}" />
                        <line x1="{{ $padX }}" y1="{{ $height - $padBottom }}" x2="{{ $width - $padX }}" y2="{{ $height - $padBottom }}" />
                    </g>

                    @if ($count > 1)
                        <polyline points="{{ $polyline }}" fill="none" stroke="#3758f9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    @endif

                    @foreach ($coords as $coord)
                        <circle cx="{{ $coord['x'] }}" cy="{{ $coord['y'] }}" r="3.2" fill="{{ $coord['point']['conforme'] ? '#13c296' : '#ef4444' }}">
                            <title>{{ $coord['point']['label'] }} : {{ $formatValue($coord['point']['valeur']) }} {{ $trend['unite'] }} ({{ $coord['point']['conforme'] ? 'conforme' : 'non conforme' }})</title>
                        </circle>
                    @endforeach

                    @if ($count === 1)
                        <text x="{{ $width / 2 }}" y="{{ $height - 6 }}" text-anchor="middle" fill="#8899a8" font-size="9">{{ $points[0]['label'] }}</text>
                    @else
                        <text x="{{ $padX }}" y="{{ $height - 6 }}" fill="#8899a8" font-size="9">{{ $points[0]['label'] }}</text>
                        <text x="{{ $width - $padX }}" y="{{ $height - 6 }}" text-anchor="end" fill="#8899a8" font-size="9">{{ $points[$count - 1]['label'] }}</text>
                    @endif
                </svg>
            </figure>
        @endforeach
    </div>
@endif
