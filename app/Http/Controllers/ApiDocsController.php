<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Serve la documentazione OpenAPI via Swagger UI (CDN).
 * Accessibile a tutti gli utenti autenticati.
 */
class ApiDocsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Pagina HTML con Swagger UI embeddato via CDN.
     */
    public function index(): Response
    {
        $specUrl = route('api-docs.spec');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tessera API Docs</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
  <style>
    body { margin: 0; background: #fafafa; }
    .topbar { background: #1e3a5f !important; }
    .topbar-wrapper img { content: url(''); display: none; }
    .topbar-wrapper::after { content: 'Tessera API — Network GTC'; color: #fff; font-size: 18px; font-weight: bold; font-family: sans-serif; }
  </style>
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
  <script>
    window.onload = function() {
      SwaggerUIBundle({
        url: "{$specUrl}",
        dom_id: '#swagger-ui',
        presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
        layout: "BaseLayout",
        deepLinking: true,
        persistAuthorization: true,
        tryItOutEnabled: false,
      });
    };
  </script>
</body>
</html>
HTML;

        return response($html)->header('Content-Type', 'text/html');
    }

    /**
     * Serve il file OpenAPI YAML.
     */
    public function spec(): Response
    {
        $path = storage_path('api-docs/openapi.yaml');

        if (! file_exists($path)) {
            abort(404, 'OpenAPI spec non trovata.');
        }

        return response(file_get_contents($path))
            ->header('Content-Type', 'application/yaml')
            ->header('Access-Control-Allow-Origin', '*');
    }
}
