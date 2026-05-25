<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Documento allegato a una ConsultantDelivery.
 *
 * Pattern identico a ConsultantRequestDocument:
 *  - non polimorfico (solo per delivery)
 *  - storage disk/path proprietari
 *  - uploaded_by_user_id traccia chi ha caricato (sempre il consulente per i delivery)
 */
class ConsultantDeliveryDocument extends Model
{
    protected $table = 'consultant_delivery_documents';

    protected $fillable = [
        'delivery_id',
        'uploaded_by_user_id',
        'filename_originale',
        'disk',
        'path',
        'size',
        'mime_type',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /* ── Relazioni ──────────────────────────────────────────────────────── */

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(ConsultantDelivery::class, 'delivery_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /* ── Helpers ────────────────────────────────────────────────────────── */

    public function sizeHuman(): string
    {
        $bytes = (int) ($this->size ?? 0);
        if ($bytes < 1024) return "{$bytes} B";
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0; $val = $bytes;
        while ($val >= 1024 && $i < count($units) - 1) {
            $val /= 1024;
            $i++;
        }
        return sprintf('%.1f %s', $val, $units[$i]);
    }

    /* ── Lifecycle: auto-delete file on record delete ──────────────────── */

    protected static function booted(): void
    {
        static::deleting(function (self $doc) {
            if ($doc->disk && $doc->path) {
                try {
                    Storage::disk($doc->disk)->delete($doc->path);
                } catch (\Throwable) {
                    // best-effort: file già rimosso o storage non raggiungibile
                }
            }
        });
    }
}
