<?php

namespace App\Exports;

use App\Models\FatturaAttiva;
use App\Models\FatturaPassiva;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class RegistroIvaExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        private readonly int $anno,
    ) {}

    public function sheets(): array
    {
        return [
            'Registro Acquisti' => new RegistroIvaAcquistiSheet($this->anno),
            'Registro Vendite'  => new RegistroIvaVenditeSheet($this->anno),
        ];
    }
}
