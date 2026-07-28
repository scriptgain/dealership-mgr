<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A parts factor or dealer. */
class Supplier extends Model
{
    use Concerns\Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'lead_time_days' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (Supplier $s) {
            if (! $s->slug) {
                $base = Str::slug($s->name) ?: 'supplier';
                $slug = $base;
                $n = 2;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$n++;
                }
                $s->slug = $slug;
            }
        });
    }

    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(Part::class)
            ->withPivot(['supplier_part_number', 'cost_cents', 'lead_time_days', 'is_preferred', 'last_quoted_at'])
            ->withTimestamps();
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class)->latest();
    }

    public function openOrderCount(): int
    {
        return $this->purchaseOrders()->whereIn('status', ['ordered', 'partial'])->count();
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
            ->orWhere('account_number', 'like', $like)
            ->orWhere('contact_name', 'like', $like));
    }
}
