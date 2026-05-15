<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ApiUsageDaily extends Model
{
    use BelongsToTenant;

    protected $table = 'api_usage_daily';

    protected $fillable = [
        'tenant_id',
        'provider',
        'endpoint',
        'method',
        'usage_date',
        'calls_count',
        'limit_count',
    ];

    protected $casts = [
        'usage_date' => 'date',
        'calls_count' => 'integer',
        'limit_count' => 'integer',
    ];
}
