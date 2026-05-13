<?php

namespace App\Http\Controllers;

use App\Exceptions\OpenApiCompanyException;
use App\Services\CompanyEnrichmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CompanyEnrichmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    /**
     * Pagina UI per ricerca aziende (IT-search).
     */
    public function searchPage(CompanyEnrichmentService $service): InertiaResponse
    {
        return Inertia::render('Anagrafica/CompanySearch', [
            'usage' => [
                'IT-search' => $service->getUsage('IT-search'),
                'IT-start'  => $service->getUsage('IT-start'),
            ],
        ]);
    }

    public function itStart(Request $request, CompanyEnrichmentService $service): JsonResponse
    {
        $request->validate([
            'identifier' => 'required|string|min:6|max:30',
            'useCache' => 'boolean',
            'forceRefresh' => 'boolean',
        ]);

        $forceRefresh = $request->boolean('forceRefresh', false);
        if ($forceRefresh && ! ($request->user()?->is_super_admin)) {
            return response()->json([
                'success' => false,
                'error' => 'Solo i super admin possono forzare il refresh.',
            ], 403);
        }

        try {
            $result = $service->enrichFromItStart(
                $request->input('identifier'),
                $request->boolean('useCache', true),
                $forceRefresh,
            );

            return response()->json($result, $result['success'] ? 200 : 429);
        } catch (OpenApiCompanyException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'usage' => $service->getUsage(),
            ], $this->httpStatus($e->getCode()));
        }
    }

    public function usage(CompanyEnrichmentService $service): JsonResponse
    {
        return response()->json([
            'IT-start'  => $service->getUsage('IT-start'),
            'IT-search' => $service->getUsage('IT-search'),
        ]);
    }

    /**
     * Ricerca aziende italiane per criteri multipli (endpoint IT-search).
     */
    public function itSearch(Request $request, CompanyEnrichmentService $service): JsonResponse
    {
        $validated = $request->validate([
            // criteri ricerca
            'companyName'      => 'nullable|string|max:255',
            'autocomplete'     => 'nullable|string|max:255',
            'province'         => 'nullable|string|size:2',
            'municipality'     => 'nullable|string|max:10',
            'atecoCode'        => 'nullable|string|max:20',
            'reaCode'          => 'nullable|string|max:30',
            'sdiCode'          => 'nullable|string|max:10',
            'legalForm'        => 'nullable|string|max:50',
            'activityStatus'   => 'nullable|in:ACTIVE,CEASED,REGISTERED,INACTIVE,SUSPENDED,UNDER_REGISTRATION',
            'pec'              => 'nullable|email|max:255',
            'taxCodeOwner'     => 'nullable|string|max:16',
            'employeesMin'     => 'nullable|integer|min:0',
            'employeesMax'     => 'nullable|integer|min:0',
            'turnoverMin'      => 'nullable|integer|min:0',
            'turnoverMax'      => 'nullable|integer|min:0',
            'creationDateFrom' => 'nullable|date_format:Y-m-d',
            'creationDateTo'   => 'nullable|date_format:Y-m-d',
            'updateDateFrom'   => 'nullable|date_format:Y-m-d',
            'updateDateTo'     => 'nullable|date_format:Y-m-d',
            'lat'              => 'nullable|numeric|between:-90,90',
            'lng'              => 'nullable|numeric|between:-180,180',
            'radius'           => 'nullable|numeric|min:0|max:200',
            // paginazione / opzioni
            'skip'             => 'nullable|integer|min:0',
            'limit'            => 'nullable|integer|min:1|max:100',
            'dryRun'           => 'nullable|boolean',
            'enrichWith'       => 'nullable|array',
            'enrichWith.*'     => 'string|in:IT-advanced,IT-pec,IT-sdicode,IT-marketing',
        ]);

        // Almeno un criterio deve essere fornito (no chiamate "vuote")
        $criteriaKeys = array_diff(array_keys($validated), ['skip', 'limit', 'dryRun', 'enrichWith']);
        $hasCriteria = collect($criteriaKeys)->contains(fn ($k) => filled($validated[$k] ?? null));
        if (! $hasCriteria) {
            return response()->json([
                'success' => false,
                'error'   => 'Fornire almeno un criterio di ricerca.',
            ], 422);
        }

        $skip       = (int) ($validated['skip'] ?? 0);
        $limit      = (int) ($validated['limit'] ?? 10);
        $dryRun     = (bool) ($validated['dryRun'] ?? false);
        $enrichWith = $validated['enrichWith'] ?? [];

        $criteria = collect($validated)
            ->except(['skip', 'limit', 'dryRun', 'enrichWith'])
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->toArray();

        try {
            $result = $service->searchItalianCompanies($criteria, $skip, $limit, $dryRun, $enrichWith);
            return response()->json($result, $result['success'] ? 200 : 429);
        } catch (OpenApiCompanyException $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
                'usage'   => $service->getUsage('IT-search'),
            ], $this->httpStatus($e->getCode()));
        }
    }

    private function httpStatus(int $code): int
    {
        return in_array($code, [204, 400, 401, 402, 404, 406, 408, 417, 429]) ? $code : 500;
    }
}
