<?php

namespace App\Exports;

use App\Models\Member;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SociSheetExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    private string $stato;
    private static array $titoli = [
        'attivo'  => 'Attivi',
        'moroso'  => 'Morosi',
        'cessato' => 'Cessati',
    ];

    public function __construct(string $stato)
    {
        $this->stato = $stato;
    }

    public function title(): string
    {
        return self::$titoli[$this->stato] ?? ucfirst($this->stato);
    }

    public function query()
    {
        return Member::query()
            ->where('stato', $this->stato)
            ->orderBy('cognome')
            ->orderBy('nome');
    }

    public function headings(): array
    {
        return ['Nome', 'Cognome', 'Email', 'Codice Fiscale', 'Data Iscrizione', 'Data Cessazione', 'Stato'];
    }

    public function map($row): array
    {
        return [
            $row->nome,
            $row->cognome,
            $row->email ?? '',
            $row->codice_fiscale ?? '',
            $row->data_iscrizione?->format('d/m/Y') ?? '',
            $row->data_cessazione?->format('d/m/Y') ?? '',
            $row->stato,
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
