<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A job the shop does repeatedly, priced once: labour time plus parts.
 */
class CannedJob extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'labour_hundredths' => 'integer',
            'labour_rate_cents' => 'integer',
            'parts_cents' => 'integer',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CannedJob $job) {
            if (! $job->slug) {
                $job->slug = static::uniqueSlug($job->name);
            }
        });
    }

    /** Deterministic slug for seeding, so re-running does not duplicate. */
    public static function uniqueSlugFor(string $name): string
    {
        return Str::slug($name) ?: 'job';
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'job';
        $slug = $base;
        $n = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(ServiceReminder::class);
    }

    /** Book time as hours, for display. */
    public function labourHours(): float
    {
        return round($this->labour_hundredths / 100, 2);
    }

    /** The rate this job bills at, falling back to the shop default. */
    public function rateCents(): int
    {
        return (int) ($this->labour_rate_cents ?: (int) (Setting::get('labour_rate_cents') ?: 12500));
    }

    public function labourCents(): int
    {
        return (int) round($this->labour_hundredths / 100 * $this->rateCents());
    }

    public function totalCents(): int
    {
        return $this->labourCents() + (int) $this->parts_cents;
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (! $term) {
            return $q;
        }

        $like = "%{$term}%";

        return $q->where(fn (Builder $x) => $x->where('name', 'like', $like)
            ->orWhere('category', 'like', $like)
            ->orWhere('description', 'like', $like));
    }
}
