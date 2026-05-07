<?php

namespace App\Exports;

use App\Models\Asset;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CespitiRegistroSheet implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    public function title(): string { return 'Registro Cespiti'; }

    public function query()
    {
        return Asset::query()
            ->with('category')
            ->orderBy('code')
            ->orderBy('name');
    }

    public function headings(): array
    {
        return ['Codice', 'Nome', 'Categoria', 'Data Acquisto', 'Costo Storico €', 'Fondo Amm. €', 'VNC €', 'Aliquota %', 'Stato'];
    }

    public function map($asset): array
    {
        $fondo = round($asset->fondoCumulato(), 2);
        $vnc   = round($asset->valoreNetto(), 2);

        return [
            $asset->code ?? '',
            $asset->name ?? '',
            $asset->category?->name ?? '',
            $asset->purchase_date?->format('d/m/Y') ?? '',
            number_format((float) ($asset->costo_storico ?? 0), 2, ',', '.'),
            number_format($fondo, 2, ',', '.'),
            number_format($vnc, 2, ',', '.'),
            number_format($asset->aliquotaEffettiva() * 100, 2) . '%',
            $asset->stato ?? '',
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
