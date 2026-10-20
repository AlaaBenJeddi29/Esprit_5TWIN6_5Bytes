<?php

namespace App\Http\Controllers\Concerns;

use DateTimeImmutable;
use Illuminate\Http\Request;

/**
 * Helpers communs aux listes paginées du Back Office : taille de page bornée
 * et plage de dates strictement validée (jamais de valeur brute du navigateur).
 */
trait ResolvesListingFilters
{
    /** @var list<int> */
    protected array $listingPerPageOptions = [10, 15, 25, 50];

    protected function listingPerPage(Request $request, int $default = 15): int
    {
        $perPage = $request->integer('per_page');

        return in_array($perPage, $this->listingPerPageOptions, true) ? $perPage : $default;
    }

    /**
     * @return array{from: ?string, to: ?string}
     */
    protected function listingDateRange(Request $request, string $fromKey = 'from', string $toKey = 'to'): array
    {
        $from = $this->validListingDate($request->input($fromKey));
        $to = $this->validListingDate($request->input($toKey));

        if ($from !== null && $to !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return ['from' => $from, 'to' => $to];
    }

    private function validListingDate(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }
}
