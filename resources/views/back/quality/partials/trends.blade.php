@php
    $formatValue = fn (float $value): string => rtrim(rtrim(number_format($value, 4, ',', ' '), '0'), ',');
@endphp

@if (empty($trends))
    <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-6 py-10 text-center dark:border-gray-700 dark:bg-white/[0.02]">
        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Aucune mesure disponible</p>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Les graphiques d'évolution apparaîtront dès le premier prélèvement analysé pour ce périmètre.</p>
    </div>
@else
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($trends as $trend)
            @php
                $points = $trend['points'];
                $count = count($points);
                $values = array_column($points, 'valeur');

                $seuilMin = $trend['seuil_min'] ?? null;
                $seuilMax = $trend['seuil_max'] ?? null;
                $scale = array_merge($values, array_values(array_filter([$seuilMin, $seuilMax], fn ($value) => $value !== null)));

                $min = (float) min($scale);
                $max = (float) max($scale);

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

                $yFor = fn (float $value): float => round($padTop + (($max - $value) / $span) * $innerHeight, 2);

                $coords = [];
                foreach ($points as $index => $point) {
                    $x = $count === 1 ? $width / 2 : $padX + ($index * $innerWidth / ($count - 1));
                    $coords[] = ['x' => round($x, 2), 'y' => $yFor($point['valeur']), 'point' => $point];
                }

                $polyline = implode(' ', array_map(fn (array $coord): string => $coord['x'].','.$coord['y'], $coords));
                $last = $coords[$count - 1];

                $seuilLines = [];
                if ($seuilMin !== null) {
                    $seuilLines[] = ['label' => 'min', 'value' => (float) $seuilMin, 'y' => $yFor((float) $seuilMin)];
                }
                if ($seuilMax !== null) {
                    $seuilLines[] = ['label' => 'max', 'value' => (float) $seuilMax, 'y' => $yFor((float) $seuilMax)];
                }
            @endphp

            <figure class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <figcaption class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $trend['label'] }}</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $count }} mesure{{ $count > 1 ? 's' : '' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-semibold text-gray-800 dark:text-white/90">
                            {{ $formatValue($last['point']['valeur']) }}
                            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">{{ $trend['unite'] }}</span>
                        </p>
                        <p class="text-[11px] text-gray-400 dark:text-gray-500">Dernière valeur</p>
                    </div>
                </figcaption>

                <svg viewBox="0 0 {{ $width }} {{ $height }}" class="mt-3 h-auto w-full" role="img" aria-label="Évolution du paramètre {{ $trend['label'] }} en {{ $trend['unite'] }}">
                    <g class="text-gray-200 dark:text-gray-700" stroke="currentColor" stroke-width="1" stroke-dasharray="3 3">
                        <line x1="{{ $padX }}" y1="{{ $padTop }}" x2="{{ $width - $padX }}" y2="{{ $padTop }}" />
                        <line x1="{{ $padX }}" y1="{{ $padTop + ($innerHeight / 2) }}" x2="{{ $width - $padX }}" y2="{{ $padTop + ($innerHeight / 2) }}" />
                        <line x1="{{ $padX }}" y1="{{ $height - $padBottom }}" x2="{{ $width - $padX }}" y2="{{ $height - $padBottom }}" />
                    </g>

                    @foreach ($seuilLines as $seuilLine)
                        <line x1="{{ $padX }}" y1="{{ $seuilLine['y'] }}" x2="{{ $width - $padX }}" y2="{{ $seuilLine['y'] }}" stroke="#98a2b3" stroke-width="1" stroke-dasharray="4 3">
                            <title>Seuil {{ $seuilLine['label'] }} : {{ $formatValue($seuilLine['value']) }} {{ $trend['unite'] }}</title>
                        </line>
                    @endforeach

                    @if ($count > 1)
                        <polyline points="{{ $polyline }}" fill="none" stroke="#465fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    @endif

                    @foreach ($coords as $coord)
                        <circle cx="{{ $coord['x'] }}" cy="{{ $coord['y'] }}" r="3.2" fill="{{ $coord['point']['conforme'] ? '#12b76a' : '#f04438' }}">
                            <title>{{ $coord['point']['label'] }} : {{ $formatValue($coord['point']['valeur']) }} {{ $trend['unite'] }} ({{ $coord['point']['conforme'] ? 'conforme' : 'non conforme' }})</title>
                        </circle>
                    @endforeach

                    @if ($count === 1)
                        <text x="{{ $width / 2 }}" y="{{ $height - 6 }}" text-anchor="middle" fill="#98a2b3" font-size="9">{{ $points[0]['label'] }}</text>
                    @else
                        <text x="{{ $padX }}" y="{{ $height - 6 }}" fill="#98a2b3" font-size="9">{{ $points[0]['label'] }}</text>
                        <text x="{{ $width - $padX }}" y="{{ $height - 6 }}" text-anchor="end" fill="#98a2b3" font-size="9">{{ $points[$count - 1]['label'] }}</text>
                    @endif
                </svg>

                @if ($seuilLines !== [])
                    <p class="mt-2 text-[11px] text-gray-400 dark:text-gray-500">
                        Seuils appliqués (dernier prélèvement) :
                        {{ implode(' · ', array_map(fn (array $line): string => $line['label'].' '.$formatValue($line['value']).' '.$trend['unite'], $seuilLines)) }}
                    </p>
                @endif
            </figure>
        @endforeach
    </div>
@endif