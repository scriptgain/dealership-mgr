<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A unit on the lot. See the migration for why this is separate from the
 * service-side `vehicles` table.
 */
class InventoryVehicle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'stock_number', 'vin', 'slug', 'year', 'make', 'model', 'trim', 'body_type',
        'condition', 'status', 'mileage', 'price', 'msrp', 'cost',
        'exterior_color', 'interior_color', 'transmission', 'drivetrain', 'fuel_type',
        'engine', 'doors', 'seats', 'mpg_city', 'mpg_highway',
        'description', 'features', 'photos', 'is_featured', 'listed_on', 'sold_on',
    ];

    protected $casts = [
        'features' => 'array',
        'photos' => 'array',
        'is_featured' => 'boolean',
        'listed_on' => 'date',
        'sold_on' => 'date',
    ];

    public const CONDITIONS = ['new' => 'New', 'used' => 'Used', 'certified' => 'Certified Pre-Owned'];
    public const STATUSES = ['available' => 'Available', 'pending' => 'Sale Pending', 'sold' => 'Sold'];

    protected static function booted(): void
    {
        static::saving(function (self $v) {
            if (blank($v->slug)) {
                $v->slug = static::uniqueSlug(trim("{$v->year} {$v->make} {$v->model} {$v->trim}").' '.$v->stock_number);
            }
        });
    }

    public static function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'vehicle';
        $slug = $base;
        $n = 1;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** "2023 Toyota RAV4 XLE Premium" */
    public function getTitleAttribute(): string
    {
        return trim("{$this->year} {$this->make} {$this->model} ".($this->trim ?? ''));
    }

    public function getShortTitleAttribute(): string
    {
        return trim("{$this->year} {$this->make} {$this->model}");
    }

    public function getConditionLabelAttribute(): string
    {
        return self::CONDITIONS[$this->condition] ?? ucfirst((string) $this->condition);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Whole-currency display price. Amounts are stored in minor units. */
    public function getPriceDisplayAttribute(): string
    {
        return $this->price === null ? 'Call For Price' : $this->formatMoney($this->price);
    }

    public function getMsrpDisplayAttribute(): ?string
    {
        return $this->msrp === null ? null : $this->formatMoney($this->msrp);
    }

    public function getMileageDisplayAttribute(): string
    {
        return $this->condition === 'new' && (int) $this->mileage < 100
            ? 'New'
            : number_format((int) $this->mileage).' mi';
    }

    /** Rough monthly payment, shown as an estimate only. */
    public function estimatedMonthly(float $apr = 6.9, int $months = 72, float $downFraction = 0.1): ?int
    {
        if (! $this->price) {
            return null;
        }

        $principal = ($this->price / 100) * (1 - $downFraction);
        $r = $apr / 100 / 12;

        return (int) round($r > 0
            ? $principal * $r / (1 - pow(1 + $r, -$months))
            : $principal / $months);
    }

    public function primaryPhoto(): ?string
    {
        return $this->photos[0] ?? null;
    }

    protected function formatMoney(int $minor): string
    {
        $symbol = config('dealership.currency_symbol', '$');
        $decimals = (int) config('dealership.currency_decimals', 2);

        return $symbol.number_format($minor / (10 ** $decimals));
    }

    // ---------------------------------------------------------------- scopes

    public function scopeListable(Builder $q): Builder
    {
        return $q->whereIn('status', ['available', 'pending']);
    }

    public function scopeAvailable(Builder $q): Builder
    {
        return $q->where('status', 'available');
    }

    public function scopeFeatured(Builder $q): Builder
    {
        return $q->where('is_featured', true);
    }
}
