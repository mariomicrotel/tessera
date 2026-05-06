<?php

namespace App\Http\Controllers;

use App\Models\EtsComplianceCheck;
use App\Services\Ets\ComplianceRuleEngine;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EtsComplianceController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,segreteria,contabile');
    }

    public function dashboard(Request $request)
    {
        $tenant = app('current_tenant');

        $ultimoCheck = EtsComplianceCheck::where('tenant_id', $tenant->id)
            ->with(['results.rule', 'runBy'])
            ->orderByDesc('run_at')
            ->first();

        $storicoChecks = EtsComplianceCheck::where('tenant_id', $tenant->id)
            ->orderByDesc('run_at')
            ->limit(10)
            ->get();

        return Inertia::render('Ets/Compliance/Dashboard', [
            'ultimoCheck'   => $ultimoCheck,
            'storicoChecks' => $storicoChecks,
            'tenant'        => $tenant->only([
                'id', 'name', 'forma_giuridica', 'runts_numero', 'runts_sezione',
                'fascia_entrate', 'ambiti_attivita', 'assicurazione_volontari_polizza',
                'assicurazione_volontari_scadenza', 'bilancio_url_pubblicazione',
            ]),
            'canRun' => auth()->user()->hasRole('admin') || auth()->user()->hasRole('segreteria'),
        ]);
    }

    public function runCheck(Request $request)
    {
        $this->middleware('role:admin,segreteria');

        $tenant = app('current_tenant');
        $engine = new ComplianceRuleEngine();
        $check  = $engine->esegui($tenant, auth()->user());

        return redirect()
            ->route('ets.compliance.show', ['check' => $check->id])
            ->with('success', 'Controllo di compliance eseguito.');
    }

    public function show(EtsComplianceCheck $check)
    {
        // Verifica che il check appartenga al tenant corrente
        abort_if($check->tenant_id !== app('current_tenant')->id, 403);

        $check->load(['results.rule', 'runBy']);

        $risultatiPerCategoria = $check->results
            ->groupBy(fn ($r) => $r->rule?->categoria ?? 'altro')
            ->map(fn ($gruppo) => $gruppo->values());

        return Inertia::render('Ets/Compliance/CheckShow', [
            'check'                 => $check,
            'risultatiPerCategoria' => $risultatiPerCategoria,
        ]);
    }
}
