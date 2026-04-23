<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Document extends Model
{
    use BelongsToTenant;

    protected $fillable = ['titolo', 'data', 'contenuto'];

    protected function casts(): array
    {
        return [
            'data' => 'date',
        ];
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
