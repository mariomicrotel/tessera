<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento allegato a una richiesta consulente.
 */
class ConsultantRequestDocument extends Model
{
    use BelongsToTenant;

    protected $table = 'consultant_request_documents';

    protected $fillable = [
        'consultant_request_id',
        'tenant_id',
        'uploaded_by_user_id',
        'filename_originale',
        'disk',
        'path',
        'size',
        'mime_type',
        'note',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    /* ── Relazioni ─────────────────────────────────────────────────────── */

    public function request(): BelongsTo
    {
        return $this->belongsTo(ConsultantRequest::class, 'consultant_request_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /* ── Helpers ───────────────────────────────────────────────────────── */

    public function sizeHuman(): string
    {
        if (! $this->size) {
            return '—';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = $this->size;
        while ($size >= 1024 && $i < 3) {
            $size /= 1024;
            $i++;
        }
        return round($size, 1).' '.$units[$i];
    }
}
