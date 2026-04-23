<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ReceiptTemplate extends Model
{
    use BelongsToTenant;

    protected $table = 'receipt_templates';

    protected $fillable = ['tipo', 'body_text'];

    public static function defaultBodyForTipo(string $tipo): string
    {
        return (string) data_get(config('receipt_templates.types', []), $tipo . '.default_text', '');
    }

    public static function getBodyForTipo(string $tipo): string
    {
        $template = self::where('tipo', $tipo)->first();
        if ($template && $template->body_text !== null && $template->body_text !== '') {
            return (string) $template->body_text;
        }

        return self::defaultBodyForTipo($tipo);
    }

    public static function render(string $tipo, array $replacements, ?string $overrideBody = null): string
    {
        $body = trim((string) ($overrideBody ?? '')) !== ''
            ? (string) $overrideBody
            : self::getBodyForTipo($tipo);

        foreach ($replacements as $key => $value) {
            $escaped = htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $body = str_replace('{{' . $key . '}}', $escaped, $body);
        }

        return $body;
    }
}
