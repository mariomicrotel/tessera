<?php

namespace App\Http\Controllers;

use App\Exports\CapitaleSocialeExport;
use App\Exports\CespitiExport;
use App\Exports\CompensaTerziExport;
use App\Exports\PrimaNotaExport;
use App\Exports\RegistroIvaExport;
use App\Exports\SociExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExcelExportController extends Controller
{
    public function soci(): BinaryFileResponse
    {
        return Excel::download(new SociExport(), 'soci_' . now()->format('Ymd') . '.xlsx');
    }

    public function primaNota(Request $request): BinaryFileResponse
    {
        $anno = $request->integer('anno', now()->year);
        return Excel::download(new PrimaNotaExport($anno), "prima_nota_{$anno}.xlsx");
    }

    public function registroIva(Request $request): BinaryFileResponse
    {
        $anno = $request->integer('anno', now()->year);
        return Excel::download(new RegistroIvaExport($anno), "registro_iva_{$anno}.xlsx");
    }

    public function capitaleSociale(): BinaryFileResponse
    {
        return Excel::download(new CapitaleSocialeExport(), 'capitale_sociale_' . now()->format('Ymd') . '.xlsx');
    }

    public function cespiti(): BinaryFileResponse
    {
        return Excel::download(new CespitiExport(), 'cespiti_' . now()->format('Ymd') . '.xlsx');
    }

    public function compensaTerzi(Request $request): BinaryFileResponse
    {
        $anno = $request->integer('anno', now()->year);
        return Excel::download(new CompensaTerziExport($anno), "compensi_terzi_{$anno}.xlsx");
    }
}
