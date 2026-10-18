<?php

namespace App\Exports\Drought;

use Illuminate\Support\Collection;

class WaterLevelExport
{
    public function __construct(protected Collection $data)
    {
    }

    /**
     * @return Collection
     */
    public function collection(): Collection
    {
        return $this->data->map(function ($item) {
            return [
                'Source' => $item->nom_source,
                'Type' => $item->typeLabel(),
                'Zone' => $item->zone?->nom ?? '—',
                'Niveau (%)' => $item->niveau,
                'Seuil Alerte (%)' => $item->seuil_alerte,
                'Seuil Critique (%)' => $item->seuil_critique,
                'Date Mesure' => $item->date_mesure?->format('d/m/Y H:i') ?? '—',
            ];
        });
    }
}
