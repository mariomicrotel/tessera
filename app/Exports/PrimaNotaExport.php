<?php

namespace App\Exports;

use App\Models\MovimentoContabile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PrimaNotaExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    public function __construct(
        private readonly int $anno,
    ) {}

    public function collection(): Collection
    {
        $movimenti = MovimentoContabile::query()
            ->with(['righe.contoContabile'])
            ->where('anno_esercizio', $this->anno)
            ->orderBy('data_registrazione')
            ->orderBy('numero')
            ->get();

        $rows = [];
        foreach ($movimenti as $mov) {
            foreach ($mov->righe as $riga) {
                $rows[] = [
                    $mov->data_registrazione?->format('d/m/Y') ?? '',
                    $mov->numero,
                    $mov->data_competenza?->format('d/m/Y') ?? '',
                    $mov->descrizione ?? '',
                    $riga->contoContabile?->descrizione ?? $riga->contoContabile?->codice ?? '',
                    $riga->descrizione ?? '',
                    number_format((float) ($riga->importo_dare ?? 0), 2, ',', '.'),
                    number_format((float) ($riga->importo_avere ?? 0), 2, ',', '.'),
                ];
            }
        }

        return collect($rows);
    }

    public function headings(): array
    {
        return ['Data Reg.', 'N°', 'Data Comp.', 'Descrizione Mov.', 'Conto', 'Descrizione Riga', 'Dare €', 'Avere €'];
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
