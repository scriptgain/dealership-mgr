<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "This truck is due for an oil change." Due by date, by odometer, or both.
 *
 * The odometer case is the useful one and the reason a vehicle tracks its own
 * mileage trend: it converts "due at 90,000 miles" into a date the shop can act
 * on, without asking the customer to report their mileage.
 */
class ServiceReminder extends Model
{
    protected $guarded = ['id'];

    public const STATUSES = ['due', 'notified', 'completed', 'dismissed'];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'due_at_miles' => 'integer',
            'last_done_miles' => 'integer',
            'last_done_on' => 'date',
            'notified_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cannedJob(): BelongsTo
    {
        return $this->belongsTo(CannedJob::class);
    }

    /** Miles still to run before this is due, negative when overdue. */
    public function milesRemaining(): ?int
    {
        if (! $this->due_at_miles) {
            return null;
        }

        $current = $this->vehicle?->currentMileage();

        return $current === null ? null : (int) $this->due_at_miles - $current;
    }

    /**
     * When the odometer target will be reached, estimated from how the vehicle
     * is actually driven. Null when there is no trend to work from, because a
     * guessed date on a service reminder is worse than no date.
     */
    public function projectedDueDate(): ?\Illuminate\Support\Carbon
    {
        $remaining = $this->milesRemaining();
        $perDay = $this->vehicle?->milesPerDay();

        if ($remaining === null || ! $perDay || $perDay <= 0) {
            return null;
        }

        return now()->addDays(max(0, (int) ceil($remaining / $perDay)));
    }

    public function isOverdue(): bool
    {
        if ($this->due_on && $this->due_on->isPast()) {
            return true;
        }

        $remaining = $this->milesRemaining();

        return $remaining !== null && $remaining <= 0;
    }

    /** The soonest of the date target and the projected odometer date. */
    public function effectiveDueDate(): ?\Illuminate\Support\Carbon
    {
        $projected = $this->projectedDueDate();

        if ($this->due_on && $projected) {
            return $this->due_on->lt($projected) ? $this->due_on : $projected;
        }

        return $this->due_on ?: $projected;
    }

    public function statusLabel(): string
    {
        if ($this->status === 'completed') {
            return 'Completed';
        }

        if ($this->status === 'dismissed') {
            return 'Dismissed';
        }

        if ($this->isOverdue()) {
            return 'Overdue';
        }

        return $this->status === 'notified' ? 'Customer Notified' : 'Upcoming';
    }

    public function statusBadge(): string
    {
        if ($this->status === 'completed') {
            return 'success';
        }

        if ($this->status === 'dismissed') {
            return 'neutral';
        }

        return $this->isOverdue() ? 'danger' : ($this->status === 'notified' ? 'info' : 'warning');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', ['due', 'notified']);
    }
}
