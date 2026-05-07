<?php

namespace App\Exports;

use App\Models\FatturaPassiva;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RegistroIvaAcquistiSheet implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(private readonly int $anno) {}

    public function title(): string { return 'Registro Acquisti'; }

    public function query()
    {
        return FatturaPassiva::query()
            ->with('supplier')
            ->whereYear('data_fattura', $this->anno)
            ->where('stato_pagamento', '!=', FatturaPassiva::STATO_ANNULLATA)
            ->orderBy('data_fattura');
    }

    public function headings(): array
    {
        return ['Data Fattura', 'N° Fattura', 'Fornitore', 'P.IVA/CF Fornitore', 'Imponibile €', 'IVA €', 'Totale €', 'Aliquota IVA %'];
    }

    public function map($f): array
    {
        return [
            $f->data_fattura?->format('d/m/Y') ?? '',
            $f->numero_fattura ?? '',
            $f->supplier?->ragione_sociale ?? $f->supplier?->name ?? '',
            $f->supplier?->partita_iva ?? $f->supplier?->codice_fiscale ?? '',
            number_format((float) ($f->imponibile_totale ?? 0), 2, ',', '.'),
            number_format((float) ($f->iva_totale ?? 0), 2, ',', '.'),
            number_format((float) ($f->totale_documento ?? 0), 2, ',', '.'),
            $f->aliquota_iva ?? '',
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
