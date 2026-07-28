<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer's vehicle. The unit of work in a repair shop: the service history
 * is the work orders that point here, and the next-service estimate comes from
 * the odometer readings recorded against those visits.
 */
class Vehicle extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'mileage' => 'integer',
            'mileage_read_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /** "2018 Chevrolet Silverado 1500" — blank parts collapse instead of leaving gaps. */
    public function getNameAttribute(): string
    {
        $parts = array_filter([$this->year, $this->make, $this->model, $this->trim]);

        return $parts ? implode(' ', $parts) : ($this->plate ? 'Plate '.$this->plate : 'Unidentified Vehicle');
    }

    /** Shops read the last 8 of a VIN aloud; the full 17 is for paperwork. */
    public function getShortVinAttribute(): ?string
    {
        return $this->vin ? strtoupper(substr($this->vin, -8)) : null;
    }

    /** Plate with its state, when both are known. */
    public function getPlateLabelAttribute(): ?string
    {
        if (! $this->plate) {
            return null;
        }

        return $this->plate_state
            ? strtoupper($this->plate).' ('.strtoupper($this->plate_state).')'
            : strtoupper($this->plate);
    }

    /**
     * Average miles per day from the recorded odometer, used to project when an
     * interval comes due. Null until there are two readings far enough apart to
     * mean anything — a made-up rate is worse than no rate.
     */
    public function milesPerDay(): ?float
    {
        $visits = $this->workOrders()
            ->whereNotNull('mileage_out')
            ->whereNotNull('completed_at')
            ->orderBy('completed_at')
            ->get(['mileage_out', 'completed_at']);

        if ($visits->count() < 2) {
            return null;
        }

        $first = $visits->first();
        $last = $visits->last();
        $days = $first->completed_at->diffInDays($last->completed_at);
        $miles = (int) $last->mileage_out - (int) $first->mileage_out;

        if ($days < 30 || $miles <= 0) {
            return null;
        }

        return round($miles / $days, 2);
    }

    /** The odometer we believe, preferring the latest completed visit. */
    /**
     * The odometer we believe, which is whichever reading is most RECENT: the
     * last completed visit, or the figure written on the vehicle itself. Simply
     * preferring the visit is wrong, because a customer who drove 2,000 miles
     * since their last service would show a number lower than their own record.
     */
    public function currentMileage(): ?int
    {
        $visit = $this->workOrders()
            ->whereNotNull('mileage_out')
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->first(['mileage_out', 'completed_at']);

        if (! $visit) {
            return $this->mileage;
        }

        if ($this->mileage === null) {
            return (int) $visit->mileage_out;
        }

        // No date on the vehicle reading means we cannot tell which came later,
        // so trust the visit, which is at least dated.
        if (! $this->mileage_read_on) {
            return (int) $visit->mileage_out;
        }

        return $this->mileage_read_on->gte($visit->completed_at)
            ? (int) $this->mileage
            : (int) $visit->mileage_out;
    }

    public function lastServicedAt(): ?\Illuminate\Support\Carbon
    {
        return $this->workOrders()->whereNotNull('completed_at')->max('completed_at')
            ? \Illuminate\Support\Carbon::parse($this->workOrders()->whereNotNull('completed_at')->max('completed_at'))
            : null;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $like = "%{$term}%";
            $q->where('vin', 'like', $like)
                ->orWhere('plate', 'like', $like)
                ->orWhere('make', 'like', $like)
                ->orWhere('model', 'like', $like)
                ->orWhere('year', 'like', $like)
                ->orWhereHas('customer', fn (Builder $c) => $c
                    ->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like));
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
