<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Host extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function getLabelAttribute(): string
    {
        return $this->team ? "{$this->name} · {$this->team}" : $this->name;
    }
}
