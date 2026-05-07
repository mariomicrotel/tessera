<?php

namespace App\Exports;

use App\Models\Member;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SociExport implements WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        return [
            'Attivi'  => new SociSheetExport('attivo'),
            'Morosi'  => new SociSheetExport('moroso'),
            'Cessati' => new SociSheetExport('cessato'),
        ];
    }
}
