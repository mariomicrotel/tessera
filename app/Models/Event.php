<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Event extends Model
{
    use BelongsToTenant;

    public const POSTER_TAG = 'poster';

    protected $fillable = ['title', 'start_at', 'end_at', 'max_participants', 'description', 'solo_soci'];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'solo_soci' => 'boolean',
        ];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** Locandina dell'evento (al massimo una, tag "poster"). */
    public function posterAttachment(): ?Attachment
    {
        return $this->attachments()->where('tag', self::POSTER_TAG)->first();
    }
}
