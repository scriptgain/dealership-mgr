<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['qty' => 'integer', 'qty_received' => 'integer', 'unit_cost_cents' => 'integer'];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function outstanding(): int
    {
        return max(0, $this->qty - $this->qty_received);
    }

    public function lineTotalCents(): int
    {
        return $this->qty * $this->unit_cost_cents;
    }
}
