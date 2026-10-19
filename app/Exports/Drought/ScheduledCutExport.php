<?php

namespace App\Exports\Drought;

use Illuminate\Support\Collection;

class ScheduledCutExport
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
        $headers = ['Motif', 'Zone', 'Infrastructure', 'Date Début', 'Date Fin', 'Statut'];
        foreach ($headers as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>' . "\n";
        }
        $html .= '</tr>' . "\n";
        
        // Data rows
        foreach ($this->data as $item) {
            $html .= '<tr>' . "\n";
            $html .= '<td>' . htmlspecialchars($item->motif) . '</td>' . "\n";
            $html .= '<td>' . htmlspecialchars($item->zone?->nom ?? '—') . '</td>' . "\n";
            $html .= '<td>' . htmlspecialchars($item->infrastructure?->nom ?? '—') . '</td>' . "\n";
            $html .= '<td>' . htmlspecialchars($item->date_debut?->format('d/m/Y H:i') ?? '—') . '</td>' . "\n";
            $html .= '<td>' . htmlspecialchars($item->date_fin?->format('d/m/Y H:i') ?? '—') . '</td>' . "\n";
            $html .= '<td>' . htmlspecialchars($item->statutLabel()) . '</td>' . "\n";
            $html .= '</tr>' . "\n";
        }
        
        $html .= '</table>' . "\n";
        $html .= '</body>' . "\n";
        $html .= '</html>' . "\n";
        
        return $html;
    }
}
