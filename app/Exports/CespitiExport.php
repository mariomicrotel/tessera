<?php

namespace App\Exports;

use App\Models\Asset;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CespitiExport implements WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        return [
            'Registro Cespiti'   => new CespitiRegistroSheet(),
            'Piano Ammortamento' => new CespitiAmmortamentoSheet(),
        ];
    }
}
