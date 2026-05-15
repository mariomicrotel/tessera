<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CompanyEnrichmentCache extends Model
{
    use BelongsToTenant;

    protected $table = 'company_enrichment_cache';

    protected $fillable = [
        'tenant_id',
        'lookup_key',
        'endpoint',
        'response_json',
        'normalized_json',
        'fetched_at',
        'expires_at',
        'source_provider',
        'response_hash',
    ];

    protected $casts = [
        'response_json' => 'array',
        'normalized_json' => 'array',
        'fetched_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
