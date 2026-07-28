<?php

namespace App\Models;

use App\Models\Concerns\HasSequentialNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** An order to a supplier, and what actually turned up. */
class PurchaseOrder extends Model
{
    use Concerns\Auditable, HasSequentialNumber;

    public const NUMBER_PREFIX = 'PO-';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expected_on' => 'date',
            'ordered_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function totalCents(): int
    {
        return (int) $this->items->sum(fn (PurchaseOrderItem $i) => $i->qty * $i->unit_cost_cents);
    }

    public function place(): void
    {
        if ($this->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only a draft order can be placed.']);
        }

        if ($this->items()->count() === 0) {
            throw ValidationException::withMessages(['status' => 'Add at least one line before placing the order.']);
        }

        $this->forceFill(['status' => 'ordered', 'ordered_at' => now()])->saveQuietly();
    }

    /**
     * Book in a delivery.
     *
     * Receiving is cumulative and capped at what was ordered, so booking the
     * same delivery in twice cannot put the stock on twice. Each receipt writes
     * its own ledger row, which is what makes a part-delivered order legible
     * three weeks later.
     */
    public function receive(array $quantities): int
    {
        if (! in_array($this->status, ['ordered', 'partial'], true)) {
            throw ValidationException::withMessages(['status' => 'Only a placed order can be received.']);
        }

        $movements = 0;

        DB::transaction(function () use ($quantities, &$movements) {
            foreach ($this->items as $item) {
                $asked = (int) ($quantities[$item->id] ?? 0);

                if ($asked <= 0) {
                    continue;
                }

                $outstanding = max(0, $item->qty - $item->qty_received);
                $take = min($asked, $outstanding);

                if ($take === 0) {
                    continue;
                }

                $item->part->move('received', $take, [
                    'unit_cost_cents' => $item->unit_cost_cents,
                    'purchase_order_item_id' => $item->id,
                    'reason' => 'Received on '.$this->number,
                ]);

                $item->forceFill(['qty_received' => $item->qty_received + $take])->saveQuietly();
                $movements++;
            }

            $this->refresh()->load('items');

            $complete = $this->items->every(fn (PurchaseOrderItem $i) => $i->qty_received >= $i->qty);

            $this->forceFill([
                'status' => $complete ? 'received' : 'partial',
                'received_at' => $complete ? now() : $this->received_at,
            ])->saveQuietly();
        });

        return $movements;
    }

    public function isFullyReceived(): bool
    {
        return $this->items->every(fn (PurchaseOrderItem $i) => $i->qty_received >= $i->qty);
    }

    public function outstandingUnits(): int
    {
        return (int) $this->items->sum(fn (PurchaseOrderItem $i) => max(0, $i->qty - $i->qty_received));
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'received' => 'success',
            'partial' => 'warning',
            'ordered' => 'info',
            'cancelled' => 'danger',
            default => 'neutral',
        };
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', ['ordered', 'partial']);
    }
}
