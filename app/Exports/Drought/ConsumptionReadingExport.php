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

class ConsumptionReadingExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
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
                $item->zone?->nom ?? '—',
                $item->periodeLabel(),
                $item->consommation_m3,
                $item->nombre_abonnes,
                $m3_per_subscriber,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Zone',
            'Période',
            'Consommation (m³)',
            'Abonnés',
            'm³/Abonné',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18,
            'B' => 15,
            'C' => 18,
            'D' => 12,
            'E' => 15,
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
