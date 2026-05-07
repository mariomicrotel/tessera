<?php

namespace App\Exports;

use App\Models\AssetDepreciationSchedule;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CespitiAmmortamentoSheet implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    public function title(): string { return 'Piano Ammortamento'; }

    public function query()
    {
        return AssetDepreciationSchedule::query()
            ->with('asset.category')
            ->orderBy('asset_id')
            ->orderBy('esercizio');
    }

    public function headings(): array
    {
        return ['Cespite', 'Categoria', 'Esercizio', 'Aliquota %', 'Fondo Inizio €', 'Quota €', 'Fondo Fine €', 'VNC Fine €', 'Stato'];
    }

    public function map($s): array
    {
        return [
            $s->asset?->name ?? "Asset #{$s->asset_id}",
            $s->asset?->category?->name ?? '',
            $s->esercizio,
            number_format((float) ($s->aliquota_applicata ?? 0) * 100, 2) . '%',
            number_format((float) ($s->fondo_inizio_anno ?? 0), 2, ',', '.'),
            number_format((float) ($s->quota_registrata ?? $s->quota_calcolata ?? 0), 2, ',', '.'),
            number_format((float) ($s->fondo_fine_anno ?? 0), 2, ',', '.'),
            number_format((float) ($s->valore_residuo_fine_anno ?? 0), 2, ',', '.'),
            $s->stato ?? '',
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
