<?php

namespace App\Http\Controllers;

use App\Services\FatturaXmlImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FatturaPassivaXmlImportController extends Controller
{
    public function __construct(private FatturaXmlImportService $service)
    {
        $this->middleware('role:admin,segreteria,contabile');
    }

    public function index(): Response
    {
        return Inertia::render('Iva/FatturePassive/ImportXml');
    }

    /**
     * Analizza il file XML e restituisce un'anteprima senza salvare.
     */
    public function preview(Request $request)
    {
        $request->validate([
            'xml' => ['required', 'file', 'mimetypes:text/xml,application/xml,text/plain', 'max:2048'],
        ]);

        try {
            $content = file_get_contents($request->file('xml')->getRealPath());
            $dati = $this->service->preview($content);
            return response()->json(['ok' => true, 'fatture' => $dati]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'Errore durante la lettura del file XML.'], 500);
        }
    }

    /**
     * Importa effettivamente le fatture dal file XML.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'xml'             => ['required', 'file', 'mimetypes:text/xml,application/xml,text/plain', 'max:2048'],
            'skip_duplicates' => ['boolean'],
        ]);

        try {
            $content = file_get_contents($request->file('xml')->getRealPath());
            $result  = $this->service->import($content, (bool) $request->boolean('skip_duplicates', true));

            $msg = count($result['created']) . ' fattura/e importata/e';
            if (count($result['skipped'])) {
                $msg .= ', ' . count($result['skipped']) . ' già presente/i ignorata/e';
            }

            return redirect()->route('iva.fatture-passive.index')
                ->with('success', $msg . '.');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['xml' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return back()->withErrors(['xml' => 'Errore durante l\'importazione: ' . $e->getMessage()]);
        }
    }
}
