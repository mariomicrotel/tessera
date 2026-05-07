<?php

namespace App\Exports;

use App\Models\CompensaTerzi;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CompensaTerziExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(
        private readonly int $anno,
    ) {}

    public function query()
    {
        return CompensaTerzi::query()
            ->where('anno_competenza', $this->anno)
            ->orderBy('nome_percipiente')
            ->orderBy('data_pagamento');
    }

    public function headings(): array
    {
        return ['Percipiente', 'CF/P.IVA', 'Data', 'Descrizione', 'Tipo', 'Imponibile €', 'Ritenuta €', 'Netto €', 'Stato Versamento'];
    }

    public function map($c): array
    {
        $netto = (float) ($c->compenso_lordo ?? 0) - (float) ($c->ritenuta ?? 0);
        return [
            $c->nome_percipiente ?? '',
            $c->codice_fiscale ?? $c->partita_iva ?? '',
            $c->data_pagamento?->format('d/m/Y') ?? '',
            $c->causale ?? '',
            $c->tipo_rapporto ?? '',
            number_format((float) ($c->compenso_lordo ?? 0), 2, ',', '.'),
            number_format((float) ($c->ritenuta ?? 0), 2, ',', '.'),
            number_format($netto, 2, ',', '.'),
            $c->stato_ritenuta ?? '',
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
