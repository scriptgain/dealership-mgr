<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One finding on an inspection: what was checked, what the technician measured,
 * how urgent it is, what it costs to put right, and what the customer said.
 *
 * Photos hang off the item rather than the inspection, because a customer
 * approving a brake job wants to see the brake, not a gallery of the whole car.
 */
class InspectionItem extends Model
{
    protected $guarded = ['id'];

    /**
     * Traffic lights, because a customer reading this on a phone in a car park
     * needs to know what is unsafe in one glance.
     */
    public const SEVERITIES = [
        'ok' => 'Checked And OK',
        'attention' => 'Needs Attention Soon',
        'urgent' => 'Needs Immediate Attention',
    ];

    public const DECISIONS = ['pending', 'approved', 'declined'];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'position' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function workOrderItem(): BelongsTo
    {
        return $this->belongsTo(WorkOrderItem::class);
    }

    public function photos(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function severityLabel(): string
    {
        return self::SEVERITIES[$this->severity] ?? 'Checked And OK';
    }

    public function severityBadge(): string
    {
        return match ($this->severity) {
            'urgent' => 'danger',
            'attention' => 'warning',
            default => 'success',
        };
    }

    /** Only a priced finding that is not already OK is worth asking about. */
    public function isActionable(): bool
    {
        return $this->severity !== 'ok' && $this->price_cents !== null;
    }

    public function decisionLabel(): string
    {
        return match ($this->decision) {
            'approved' => 'Approved',
            'declined' => 'Declined',
            default => 'Awaiting Decision',
        };
    }

    public function decisionBadge(): string
    {
        return match ($this->decision) {
            'approved' => 'success',
            'declined' => 'neutral',
            default => 'warning',
        };
    }
}
