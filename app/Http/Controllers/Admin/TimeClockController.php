<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The shop floor clock: who is on what, and what it added up to. */
class TimeClockController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->date('date') ?? now();

        // Entries that started on the day, plus anything still running from
        // an earlier one, because a forgotten clock-on is exactly what this
        // screen exists to surface.
        $entries = TimeEntry::with(['user', 'workOrder'])
            ->where(fn ($q) => $q->forDay($date)->orWhereNull('ended_at'))
            ->get();

        return view('admin.time.index', [
            'date' => $date,
            'open' => TimeEntry::open()->with(['user', 'workOrder'])->orderBy('started_at')->get(),
            'entries' => $entries->sortByDesc('started_at'),
            'byTech' => $entries->groupBy('user_id')->map(fn ($rows) => [
                'user' => $rows->first()->user,
                'clocked' => $rows->sum(fn (TimeEntry $e) => $e->elapsedMinutes()),
                'billed' => $rows->sum('billed_hundredths'),
            ])->sortByDesc('clocked'),
            'technicians' => User::orderBy('name')->get(),
            'workOrders' => WorkOrder::whereIn('status', ['scheduled', 'in_progress'])->latest()->limit(50)->get(),
        ]);
    }

    public function clockOn(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'work_order_id' => ['required', 'integer', 'exists:work_orders,id'],
            'activity' => ['nullable', Rule::in(TimeEntry::ACTIVITIES)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            TimeEntry::clockOn(
                User::findOrFail($data['user_id']),
                WorkOrder::findOrFail($data['work_order_id']),
                ['activity' => $data['activity'] ?? 'labour', 'note' => $data['note'] ?? null],
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'Clocked on.');
    }

    public function clockOff(Request $request, TimeEntry $entry)
    {
        $data = $request->validate([
            'billed_hundredths' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $entry->clockOff($data['billed_hundredths'] ?? null, $data['note'] ?? null);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'Clocked off at '.$entry->fresh()->hoursLabel().'.');
    }
}
