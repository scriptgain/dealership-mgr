<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of the stock ledger. Never edited, only added to. */
class PartMovement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['qty' => 'integer', 'unit_cost_cents' => 'integer'];
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return match ($this->kind) {
            'received' => 'Received',
            'issued' => 'Issued To Job',
            'returned' => 'Returned To Stock',
            'scrapped' => 'Scrapped',
            default => 'Adjusted',
        };
    }

    public function badge(): string
    {
        return match ($this->kind) {
            'received', 'returned' => 'success',
            'issued' => 'info',
            'scrapped' => 'danger',
            default => 'warning',
        };
    }
}
