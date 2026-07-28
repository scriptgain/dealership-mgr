<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A technician's clock.
 *
 * The invariant is one open entry per technician. Not zero, which loses the
 * hour; not two, which is how a shop bills more labour than the day contains
 * and only finds out when a customer adds the ticket up.
 */
class TimeEntry extends Model
{
    use Concerns\Auditable;

    public const ACTIVITIES = ['labour', 'diagnosis', 'road_test', 'admin'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'minutes' => 'integer',
            'billed_hundredths' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function workOrderItem(): BelongsTo
    {
        return $this->belongsTo(WorkOrderItem::class);
    }

    public static function openFor(User $user): ?self
    {
        return static::where('user_id', $user->id)->whereNull('ended_at')->latest('started_at')->first();
    }

    /**
     * Clock on. Refuses while the technician is already on a job, rather than
     * silently closing the other one: a tech who has forgotten to clock off is
     * a conversation, not something to paper over.
     */
    public static function clockOn(User $user, WorkOrder $workOrder, array $attributes = []): self
    {
        return DB::transaction(function () use ($user, $workOrder, $attributes) {
            $open = static::where('user_id', $user->id)->whereNull('ended_at')
                ->lockForUpdate()->first();

            if ($open) {
                throw ValidationException::withMessages([
                    'work_order_id' => $user->name.' is already clocked on to '
                        .($open->workOrder?->number ?? 'another job')
                        .' since '.$open->started_at->format('H:i').'. Clock off there first.',
                ]);
            }

            return static::create($attributes + [
                'user_id' => $user->id,
                'work_order_id' => $workOrder->id,
                'started_at' => now(),
                'activity' => $attributes['activity'] ?? 'labour',
            ]);
        });
    }

    public function clockOff(?int $billedHundredths = null, ?string $note = null): void
    {
        if ($this->ended_at) {
            throw ValidationException::withMessages(['ended_at' => 'That entry is already closed.']);
        }

        $ended = now();

        $this->forceFill([
            'ended_at' => $ended,
            'minutes' => max(0, (int) $this->started_at->diffInMinutes($ended)),
            // Billed time defaults to clock time, but a canned job may say
            // otherwise and the two are deliberately separate columns.
            'billed_hundredths' => $billedHundredths ?? (int) round($this->started_at->diffInMinutes($ended) / 60 * 100),
            'note' => $note ?? $this->note,
        ])->saveQuietly();
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    public function elapsedMinutes(): int
    {
        return $this->minutes ?? max(0, (int) $this->started_at->diffInMinutes(now()));
    }

    public function hoursLabel(): string
    {
        $hundredths = $this->billed_hundredths ?? (int) round($this->elapsedMinutes() / 60 * 100);

        return number_format($hundredths / 100, 2).' hr';
    }

    public function activityLabel(): string
    {
        return match ($this->activity) {
            'diagnosis' => 'Diagnosis',
            'road_test' => 'Road Test',
            'admin' => 'Admin',
            default => 'Labour',
        };
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNull('ended_at');
    }

    public function scopeForDay(Builder $q, $date): Builder
    {
        return $q->whereDate('started_at', $date);
    }
}
