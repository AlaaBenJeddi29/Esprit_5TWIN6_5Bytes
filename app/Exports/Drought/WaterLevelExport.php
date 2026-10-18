<?php

namespace App\Exports\Drought;

use Illuminate\Support\Collection;

class WaterLevelExport
{
    public function __construct(protected Collection $data)
    {
    }

    /**
     * Generate Excel-compatible HTML
     */
    public function toHtml(): string
    {
        $html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel">' . "\n";
        $html .= '<head>' . "\n";
        $html .= '<meta charset="UTF-8">' . "\n";
        $html .= '<style>' . "\n";
        $html .= 'table { border-collapse: collapse; width: 100%; }' . "\n";
        $html .= 'th, td { border: 1px solid #000; padding: 8px; text-align: left; }' . "\n";
        $html .= 'th { background-color: #1A5276; color: white; font-weight: bold; text-align: center; }' . "\n";
        $html .= 'tr:nth-child(even) { background-color: #f2f2f2; }' . "\n";
        $html .= '</style>' . "\n";
        $html .= '</head>' . "\n";
        $html .= '<body>' . "\n";
        $html .= '<table>' . "\n";
        
        // Headers
        $html .= '<tr>' . "\n";
        $headers = ['Source', 'Type', 'Zone', 'Niveau (%)', 'Seuil Alerte (%)', 'Seuil Critique (%)', 'Date Mesure'];
        foreach ($headers as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>' . "\n";
        }
        $html .= '</tr>' . "\n";
        
        // Data rows
        foreach ($this->data as $item) {
            $html .= '<tr>' . "\n";
            $html .= '<td>' . htmlspecialchars($item->nom_source) . '</td>' . "\n";
            $html .= '<td>' . htmlspecialchars($item->typeLabel()) . '</td>' . "\n";
            $html .= '<td>' . htmlspecialchars($item->zone?->nom ?? '—') . '</td>' . "\n";
            $html .= '<td>' . htmlspecialchars((string)$item->niveau) . '</td>' . "\n";
            $html .= '<td>' . htmlspecialchars((string)$item->seuil_alerte) . '</td>' . "\n";
            $html .= '<td>' . htmlspecialchars((string)$item->seuil_critique) . '</td>' . "\n";
            $html .= '<td>' . htmlspecialchars($item->date_mesure?->format('d/m/Y H:i') ?? '—') . '</td>' . "\n";
            $html .= '</tr>' . "\n";
        }
        
        $html .= '</table>' . "\n";
        $html .= '</body>' . "\n";
        $html .= '</html>' . "\n";
        
        return $html;
    }
}
