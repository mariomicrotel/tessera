<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use BelongsToTenant;

    protected $table = 'email_templates';

    protected $fillable = ['tipo', 'subject', 'body_html'];

    /**
     * Sostituisce i placeholder {{key}} in subject e body_html con i valori in $replacements.
     * Nel body i valori sono escapati per HTML. Restituisce null se il template non esiste.
     */
    public static function render(string $tipo, array $replacements): ?array
    {
        $template = self::where('tipo', $tipo)->first();
        if (! $template) {
            return null;
        }

        $subject = self::replacePlaceholders($template->subject, $replacements, false);
        $body = self::replacePlaceholders($template->body_html ?? '', $replacements, true);

        return ['subject' => $subject, 'body' => $body];
    }

    private static function replacePlaceholders(string $text, array $replacements, bool $escapeHtml): string
    {
        foreach ($replacements as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $value = (string) $value;
            if ($escapeHtml) {
                $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            $text = str_replace($placeholder, $value, $text);
        }

        return $text;
    }
}
