<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * Allegato polimorfico: referenziabile da più entità (settings/logo, member, ecc.).
 */
class Attachment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'tag',
        'file_path',
        'original_name',
        'mime_type',
        'size',
        'disk',
    ];

    protected $hidden = ['file_path'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Scope: allegati per "setting" (attachable_type=setting, attachable_id null) con tag dato. */
    public function scopeForSetting($query, string $tag)
    {
        return $query->where('attachable_type', 'setting')
            ->whereNull('attachable_id')
            ->where('tag', $tag);
    }

    /** URL per visualizzare/scaricare l'allegato (route attachments.show). */
    public function url(): string
    {
        return route('attachments.show', $this);
    }

    /**
     * Contenuto del file come data URI (base64) per embedding in PDF.
     */
    public function toDataUri(): ?string
    {
        if (! $this->file_path || ! Storage::disk($this->disk)->exists($this->file_path)) {
            return null;
        }
        $content = Storage::disk($this->disk)->get($this->file_path);
        $mime = $this->mime_type ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }

    /**
     * Data URI del logo associazione per PDF (null se non configurato).
     */
    public static function logoDataUriForPdf(): ?string
    {
        $attachment = static::forSetting('logo')->first();

        return $attachment?->toDataUri();
    }

    protected static function booted(): void
    {
        static::deleting(function (Attachment $attachment) {
            if ($attachment->file_path && $attachment->disk) {
                Storage::disk($attachment->disk)->delete($attachment->file_path);
            }
        });
    }
}
