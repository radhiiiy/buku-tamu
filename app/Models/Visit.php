<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Visit extends Model
{
    public const STATUSES = [
        'menunggu' => 'Menunggu',
        'berkunjung' => 'Berkunjung',
        'selesai' => 'Selesai',
        'ditolak' => 'Ditolak',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'arrived_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'consented_at' => 'datetime',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(VisitEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function getCodeAttribute(): string
    {
        return sprintf('TAMU-%04d', $this->id);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function getDurationMinutesAttribute(): ?int
    {
        if (! $this->checked_out_at) {
            return null;
        }

        return (int) $this->arrived_at->diffInMinutes($this->checked_out_at, true);
    }

    public function scopeOnDate(Builder $q, Carbon|string $date): Builder
    {
        $d = Carbon::parse($date);

        return $q->whereBetween('arrived_at', [$d->copy()->startOfDay(), $d->copy()->endOfDay()]);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $q;
        }

        return $q->where(function (Builder $w) use ($term) {
            $like = '%'.$term.'%';
            $w->where('guest_name', 'like', $like)->orWhere('guest_company', 'like', $like);
            if (preg_match('/^#?(?:TAMU-)?0*(\d+)$/i', $term, $m)) {
                $w->orWhere('id', (int) $m[1]);
            }
        });
    }
}
