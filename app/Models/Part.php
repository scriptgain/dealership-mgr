<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A part.
 *
 * on_hand is not a column. It is the sum of the movement ledger, so the figure
 * on screen and the reason for it are the same object. A shop that stores the
 * count as a mutable integer can tell you it has three; it cannot tell you why
 * it does not have five.
 */
class Part extends Model
{
    use Concerns\Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cost_cents' => 'integer',
            'price_cents' => 'integer',
            'core_charge_cents' => 'integer',
            'reorder_point' => 'integer',
            'reorder_qty' => 'integer',
            'is_stocked' => 'boolean',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Part $p) {
            if (! $p->slug) {
                $base = Str::slug($p->part_number.'-'.$p->name) ?: 'part';
                $slug = $base;
                $n = 2;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$n++;
                }
                $p->slug = $slug;
            }
        });
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class)
            ->withPivot(['supplier_part_number', 'cost_cents', 'lead_time_days', 'is_preferred', 'last_quoted_at'])
            ->withTimestamps();
    }

    public function movements(): HasMany
    {
        return $this->hasMany(PartMovement::class)->latest();
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(Part::class, 'superseded_by_part_id');
    }

    public function supersedes(): HasMany
    {
        return $this->hasMany(Part::class, 'superseded_by_part_id');
    }

    public function onHand(): int
    {
        return (int) $this->movements()->sum('qty');
    }

    /** The factor to ring first: the one marked preferred, else the cheapest. */
    public function preferredSupplier(): ?Supplier
    {
        $suppliers = $this->suppliers;

        return $suppliers->firstWhere('pivot.is_preferred', true)
            ?? $suppliers->sortBy(fn (Supplier $s) => $s->pivot->cost_cents ?? PHP_INT_MAX)->first();
    }

    public function bestCostCents(): int
    {
        $quoted = $this->suppliers->pluck('pivot.cost_cents')->filter()->min();

        return (int) ($quoted ?? $this->cost_cents);
    }

    /** Gross margin on the shelf price, which is what a shop lives on. */
    public function marginPercent(): ?float
    {
        if (! $this->price_cents) {
            return null;
        }

        return round(($this->price_cents - $this->bestCostCents()) / $this->price_cents * 100, 1);
    }

    public function isBelowReorderPoint(): bool
    {
        return $this->reorder_point !== null && $this->onHand() <= $this->reorder_point;
    }

    /** Quantity on open purchase orders, which is why a low count may be fine. */
    public function onOrder(): int
    {
        return (int) PurchaseOrderItem::query()
            ->where('part_id', $this->id)
            ->whereHas('purchaseOrder', fn (Builder $q) => $q->whereIn('status', ['ordered', 'partial']))
            ->get()
            ->sum(fn (PurchaseOrderItem $i) => max(0, $i->qty - $i->qty_received));
    }

    /**
     * Move stock. Every change to the count goes through here, so the ledger
     * and the count cannot disagree.
     */
    public function move(string $kind, int $qty, array $attributes = []): PartMovement
    {
        return $this->movements()->create($attributes + [
            'kind' => $kind,
            'qty' => $qty,
            'user_id' => auth()->id(),
        ]);
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

        return $q->where(fn (Builder $x) => $x->where('part_number', 'like', $like)
            ->orWhere('name', 'like', $like)
            ->orWhere('brand', 'like', $like)
            ->orWhere('bin_location', 'like', $like));
    }
}
