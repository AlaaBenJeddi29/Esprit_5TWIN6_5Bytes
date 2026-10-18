<?php

namespace App\Exports\Drought;

use Illuminate\Support\Collection;

class ScheduledCutExport
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
                'Motif' => $item->motif,
                'Zone' => $item->zone?->nom ?? '—',
                'Infrastructure' => $item->infrastructure?->nom ?? '—',
                'Date Début' => $item->date_debut?->format('d/m/Y H:i') ?? '—',
                'Date Fin' => $item->date_fin?->format('d/m/Y H:i') ?? '—',
                'Statut' => $item->statutLabel(),
            ];
        });
    }
}
