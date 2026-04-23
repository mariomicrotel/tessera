<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * File manager in sola lettura per storage/app/private/media.
 * Nomi cartelle e file = nomi reali sul filesystem (nessuna traduzione a runtime).
 */
class MediaController extends Controller
{
    private const MEDIA_BASE = 'media';

    public function __construct()
    {
        $this->middleware('role:admin,segreteria');
    }

    /**
     * Normalizza e valida il path relativo sotto media (no .., no path assoluti).
     */
    private function normalizePath(?string $path): string
    {
        if ($path === null || $path === '') {
            return '';
        }
        $path = str_replace('\\', '/', trim($path));
        if (str_contains($path, '..')) {
            abort(400, 'Path non valido.');
        }
        $segments = array_filter(explode('/', $path), fn ($s) => $s !== '');
        return implode('/', $segments);
    }

    /**
     * Elenco cartelle e file nella directory media (o sottopath).
     */
    public function index(Request $request)
    {
        $currentPath = $this->normalizePath($request->input('path'));
        $dir = $currentPath === '' ? self::MEDIA_BASE : self::MEDIA_BASE . '/' . $currentPath;

        if (! Storage::disk('local')->exists($dir)) {
            if ($currentPath === '') {
                Storage::disk('local')->makeDirectory(self::MEDIA_BASE);
            } else {
                abort(404, 'Cartella non trovata.');
            }
        }
        if (! Storage::disk('local')->directoryExists($dir)) {
            abort(400, 'Il path non è una cartella.');
        }

        $items = [];
        $disk = Storage::disk('local');

        foreach ($disk->directories($dir) as $fullPath) {
            $name = basename($fullPath);
            $relativePath = $currentPath === '' ? $name : $currentPath . '/' . $name;
            $items[] = [
                'type' => 'folder',
                'name' => $name,
                'path' => $relativePath,
            ];
        }

        foreach ($disk->files($dir) as $fullPath) {
            $name = basename($fullPath);
            $relativePath = $currentPath === '' ? $name : $currentPath . '/' . $name;
            $size = null;
            try {
                $size = $disk->size($fullPath);
            } catch (\Throwable) {
                // ignora errori di size
            }
            $items[] = [
                'type' => 'file',
                'name' => $name,
                'path' => $relativePath,
                'size' => $size,
            ];
        }

        // Filtro opzionale per nome (case-insensitive, contiene)
        $search = $request->input('search', $request->input('q', ''));
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $items = array_values(array_filter($items, function ($item) use ($needle) {
                return str_contains(mb_strtolower($item['name']), $needle);
            }));
        }

        // Paginazione sull'array (30–50 per pagina)
        $perPage = 40;
        $currentPage = (int) $request->input('page', 1);
        $total = count($items);
        $slice = array_slice($items, ($currentPage - 1) * $perPage, $perPage);
        $paginator = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Breadcrumb: root "I miei file" + segmenti con nome reale (cartella sul disco)
        $breadcrumb = [['path' => '', 'label' => 'I miei file']];
        if ($currentPath !== '') {
            $segments = explode('/', $currentPath);
            $acc = '';
            foreach ($segments as $segment) {
                $acc = $acc === '' ? $segment : $acc . '/' . $segment;
                $breadcrumb[] = ['path' => $acc, 'label' => $segment];
            }
        }

        return Inertia::render('File/Index', [
            'items' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'prev_page_url' => $paginator->previousPageUrl(),
                'next_page_url' => $paginator->nextPageUrl(),
            ],
            'breadcrumb' => $breadcrumb,
            'currentPath' => $currentPath,
            'filters' => $request->only('search', 'q'),
        ]);
    }

    /**
     * Anteprima inline di un file (per img/iframe nel pannello preview).
     */
    public function preview(Request $request)
    {
        $path = $this->normalizePath($request->input('path'));
        if ($path === '') {
            abort(400, 'Path file obbligatorio.');
        }
        $fullPath = self::MEDIA_BASE . '/' . $path;

        if (! Storage::disk('local')->exists($fullPath)) {
            abort(404, 'File non trovato.');
        }
        if (Storage::disk('local')->directoryExists($fullPath)) {
            abort(400, 'L\'anteprima è consentita solo per i file.');
        }

        $filename = basename($path);
        $disk = Storage::disk('local');
        $mime = $disk->mimeType($fullPath) ?: 'application/octet-stream';
        $pathOnDisk = $disk->path($fullPath);

        return response()->file($pathOnDisk, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
        ]);
    }

    /**
     * Download di un file sotto media.
     */
    public function download(Request $request)
    {
        $path = $this->normalizePath($request->input('path'));
        if ($path === '') {
            abort(400, 'Path file obbligatorio.');
        }
        $fullPath = self::MEDIA_BASE . '/' . $path;

        if (! Storage::disk('local')->exists($fullPath)) {
            abort(404, 'File non trovato.');
        }
        if (Storage::disk('local')->directoryExists($fullPath)) {
            abort(400, 'Il download è consentito solo per i file.');
        }

        $filename = basename($path);

        return Storage::disk('local')->download($fullPath, $filename);
    }
}
