<?php

namespace App\Exports;

use App\Models\CooperativeShare;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CapitaleSocialeExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function query()
    {
        return CooperativeShare::query()
            ->with('member')
            ->orderBy('member_id');
    }

    public function headings(): array
    {
        return ['Socio', 'N° Quote', 'Sottoscritto €', 'Versato €', 'Da versare €', '% Versato', 'Stato'];
    }

    public function map($s): array
    {
        $daVersare  = max(0, (float) $s->totale_sottoscritto - (float) $s->totale_versato);
        $percentuale = $s->totale_sottoscritto > 0
            ? round(((float) $s->totale_versato / (float) $s->totale_sottoscritto) * 100, 1)
            : 0;

        return [
            $s->member ? ($s->member->cognome . ' ' . $s->member->nome) : "Socio #{$s->member_id}",
            $s->numero_quote,
            number_format((float) $s->totale_sottoscritto, 2, ',', '.'),
            number_format((float) $s->totale_versato, 2, ',', '.'),
            number_format($daVersare, 2, ',', '.'),
            $percentuale . '%',
            $s->status,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E40AF']],
            ],
        ];
    }
}
