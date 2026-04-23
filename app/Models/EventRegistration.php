<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRegistration extends Model
{
    use BelongsToTenant;

    protected $fillable = ['event_id', 'member_id', 'guest_name', 'registered_at', 'status'];

    protected $appends = ['display_name'];

    protected function casts(): array
    {
        return ['registered_at' => 'datetime'];
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->relationLoaded('member') && $this->member) {
            return trim($this->member->cognome . ' ' . $this->member->nome);
        }
        return (string) ($this->guest_name ?? '');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
