<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasSequentialNumber;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/**
 * A digital vehicle inspection: what the technician found, with photos, priced,
 * and sent to the customer to approve line by line.
 */
class Inspection extends Model
{
    use Auditable;
    use HasSequentialNumber;
    use RecordsActivity;

    public const NUMBER_PREFIX = 'INS-';

    public const STATUSES = ['draft', 'sent', 'reviewed', 'closed'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'mileage' => 'integer',
            'sent_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Inspection $inspection) {
            if (! $inspection->review_token) {
                $inspection->review_token = Str::random(48);
            }
        });
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InspectionItem::class)->orderBy('position');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** The customer-facing review link. Unguessable, no account needed. */
    public function reviewUrl(): string
    {
        return route('shop.inspection.public', $this->review_token);
    }

    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    /** A customer can only act while the inspection is out for review. */
    public function isOpenForReview(): bool
    {
        return $this->isSent() && $this->status !== 'closed';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'sent' => 'Awaiting Customer',
            'reviewed' => 'Customer Responded',
            'closed' => 'Closed',
            default => Str::headline((string) $this->status),
        };
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'draft' => 'neutral',
            'sent' => 'warning',
            'reviewed' => 'info',
            'closed' => 'success',
            default => 'neutral',
        };
    }

    /** Counts the customer's answers, which is what the shop actually wants to see. */
    public function decisionCounts(): array
    {
        $items = $this->items;

        return [
            'approved' => $items->where('decision', 'approved')->count(),
            'declined' => $items->where('decision', 'declined')->count(),
            'pending' => $items->where('decision', 'pending')->count(),
        ];
    }

    /** Value of the work the customer said yes to, in cents. */
    public function approvedTotalCents(): int
    {
        return (int) $this->items->where('decision', 'approved')->sum('price_cents');
    }

    /** Everything priced, whether or not it was approved. */
    public function recommendedTotalCents(): int
    {
        return (int) $this->items->whereNotNull('price_cents')->sum('price_cents');
    }

    /** Approved lines not yet pushed onto the repair order. */
    public function approvedNotYetBilled()
    {
        return $this->items->where('decision', 'approved')->whereNull('work_order_item_id');
    }
}
