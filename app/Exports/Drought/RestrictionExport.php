<?php

namespace App\Exports\Drought;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class RestrictionExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
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
                $item->titre,
                $item->typeLabel(),
                $item->zone?->nom ?? '—',
                $item->date_debut?->format('d/m/Y H:i') ?? '—',
                $item->date_fin?->format('d/m/Y H:i') ?? '—',
                $item->statutLabel(),
                $item->usages_interdits ?? '—',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Titre',
            'Niveau',
            'Zone',
            'Date Début',
            'Date Fin',
            'Statut',
            'Usages Interdits',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 15,
            'C' => 18,
            'D' => 18,
            'E' => 18,
            'F' => 12,
            'G' => 25,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [
            // Header row formatting
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1A5276'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ],
        ];

        // Apply borders to all rows with data
        $highestRow = $sheet->getHighestRow();
        if ($highestRow > 1) {
            $borderStyle = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ];

            for ($row = 2; $row <= $highestRow; $row++) {
                $styles[$row] = $borderStyle;
            }
        }

        return $styles;
    }
}
