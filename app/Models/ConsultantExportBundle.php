<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bundle di export dati operativi che un consulente richiede per un tenant.
 *
 * Cross-tenant: niente BelongsToTenant — il consulente può avere bundle di N tenant.
 * Le query devono sempre filtrare per `consultant_user_id` lato controller.
 *
 * Lifecycle: pending → processing → (ready | failed | cancelled) → expired
 */
class ConsultantExportBundle extends Model
{
    use HasUuids;

    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY      = 'ready';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_CANCELLED  = 'cancelled';
    public const STATUS_EXPIRED    = 'expired';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_READY,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
        self::STATUS_EXPIRED,
    ];

    /** TTL del file generato (giorni). */
    public const DEFAULT_TTL_DAYS = 30;

    protected $table = 'consultant_export_bundles';

    protected $fillable = [
        'tenant_id',
        'consultant_user_id',
        'period_from',
        'period_to',
        'formats',
        'data_types',
        'status',
        'file_path',
        'file_size_bytes',
        'download_count',
        'error_message',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'period_from'     => 'date',
            'period_to'       => 'date',
            'formats'         => 'array',
            'data_types'      => 'array',
            'file_size_bytes' => 'integer',
            'download_count'  => 'integer',
            'started_at'      => 'datetime',
            'completed_at'    => 'datetime',
            'expires_at'      => 'datetime',
        ];
    }

    /* ── Relazioni ────────────────────────────────────────────────────────── */

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_user_id');
    }

    /* ── Stato ────────────────────────────────────────────────────────────── */

    public function isPending(): bool    { return $this->status === self::STATUS_PENDING; }
    public function isProcessing(): bool { return $this->status === self::STATUS_PROCESSING; }
    public function isReady(): bool      { return $this->status === self::STATUS_READY; }
    public function isFailed(): bool     { return $this->status === self::STATUS_FAILED; }
    public function isCancelled(): bool  { return $this->status === self::STATUS_CANCELLED; }
    public function isExpired(): bool    { return $this->status === self::STATUS_EXPIRED; }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_READY,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED,
        ], true);
    }

    /**
     * Vero se il file è ancora presente e scaricabile.
     * Controlla sia lo stato che la presenza fisica del file.
     */
    public function isDownloadable(): bool
    {
        if (! $this->isReady()) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        if (! $this->file_path) {
            return false;
        }
        return \Illuminate\Support\Facades\Storage::disk(static::storageDisk())->exists($this->file_path);
    }

    /* ── Helpers ─────────────────────────────────────────────────────────── */

    /**
     * Disk su cui salvare gli export. Astratto via Storage facade per poter
     * passare da local a S3 in futuro senza toccare il codice.
     * Configurato in config/filesystems.php sotto 'consultant_exports'.
     */
    public static function storageDisk(): string
    {
        return config('filesystems.disks.consultant_exports') ? 'consultant_exports' : 'local';
    }

    /**
     * Path relativo del file all'interno del disk.
     * Pattern: consultant-exports/{tenant_id}/{YYYY-MM-DD}-{bundle_id}.zip
     */
    public function generateFilePath(): string
    {
        return sprintf(
            'consultant-exports/%s/%s-%s.zip',
            $this->tenant_id,
            now()->format('Y-m-d'),
            $this->id,
        );
    }

    /**
     * Etichetta breve per la UI (es. "01/01/2026 → 31/12/2026").
     */
    public function periodLabel(): string
    {
        return sprintf(
            '%s → %s',
            $this->period_from?->format('d/m/Y') ?? '?',
            $this->period_to?->format('d/m/Y') ?? '?',
        );
    }

    /**
     * Dimensione file leggibile (es. "12.5 MB").
     */
    public function fileSizeHuman(): ?string
    {
        if (! $this->file_size_bytes) {
            return null;
        }
        $bytes = $this->file_size_bytes;
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return sprintf('%.1f %s', $bytes, $unit);
            }
            $bytes /= 1024;
        }
        return sprintf('%.1f TB', $bytes);
    }
}
