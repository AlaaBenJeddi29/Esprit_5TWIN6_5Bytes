<?php

namespace App\Exports\Drought;

use Illuminate\Support\Collection;

class ConsumptionReadingExport
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
            $m3_per_subscriber = $item->nombre_abonnes > 0 
                ? round($item->consommation_m3 / $item->nombre_abonnes, 2)
                : 0;
            
            return [
                'Zone' => $item->zone?->nom ?? '—',
                'Période' => $item->periodeLabel(),
                'Consommation (m³)' => $item->consommation_m3,
                'Abonnés' => $item->nombre_abonnes,
                'm³/Abonné' => $m3_per_subscriber,
            ];
        });
    }
}
