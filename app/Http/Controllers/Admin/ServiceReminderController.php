<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CannedJob;
use App\Models\ServiceReminder;
use App\Models\Vehicle;
use Illuminate\Http\Request;

/** Reminders that bring a vehicle back, due by date or by odometer. */
class ServiceReminderController extends Controller
{
    public function index(Request $request)
    {
        $state = $request->string('state')->toString() ?: 'open';

        $reminders = ServiceReminder::with(['vehicle.customer', 'cannedJob'])
            ->when($state === 'open', fn ($q) => $q->open())
            ->when($state === 'completed', fn ($q) => $q->where('status', 'completed'))
            ->when($state === 'dismissed', fn ($q) => $q->where('status', 'dismissed'))
            ->orderByRaw('COALESCE(due_on, "2099-12-31") ASC')
            ->paginate((int) config('dealership.rows_per_page', 25))
            ->withQueryString();

        // Overdue first is what a service writer actually wants, and that cannot
        // be expressed in SQL here because the odometer half is computed.
        $sorted = $reminders->getCollection()
            ->sortByDesc(fn (ServiceReminder $r) => $r->isOverdue() ? 1 : 0)
            ->values();
        $reminders->setCollection($sorted);

        return view('admin.reminders.index', [
            'reminders' => $reminders,
            'state' => $state,
            'counts' => [
                'open' => ServiceReminder::open()->count(),
                'completed' => ServiceReminder::where('status', 'completed')->count(),
                'dismissed' => ServiceReminder::where('status', 'dismissed')->count(),
            ],
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.reminders.create', [
            'reminder' => new ServiceReminder(['vehicle_id' => $request->integer('vehicle') ?: null]),
            'vehicles' => Vehicle::with('customer')->active()->orderByDesc('updated_at')->get(),
            'jobs' => CannedJob::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $vehicle = Vehicle::findOrFail($data['vehicle_id']);

        ServiceReminder::create($data + [
            'customer_id' => $vehicle->customer_id,
            'status' => 'due',
        ]);

        return redirect()->route('reminders.index')->with('status', 'Reminder created.');
    }

    public function edit(ServiceReminder $reminder)
    {
        return view('admin.reminders.edit', [
            'reminder' => $reminder,
            'vehicles' => Vehicle::with('customer')->active()->orderByDesc('updated_at')->get(),
            'jobs' => CannedJob::active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, ServiceReminder $reminder)
    {
        $reminder->update($this->validated($request));

        return redirect()->route('reminders.index')->with('status', 'Reminder updated.');
    }

    public function complete(ServiceReminder $reminder)
    {
        $reminder->update([
            'status' => 'completed',
            'completed_at' => now(),
            'last_done_miles' => $reminder->vehicle?->currentMileage(),
            'last_done_on' => now()->toDateString(),
        ]);

        return back()->with('status', 'Reminder marked done.');
    }

    public function dismiss(ServiceReminder $reminder)
    {
        $reminder->update(['status' => 'dismissed']);

        return back()->with('status', 'Reminder dismissed.');
    }

    public function destroy(ServiceReminder $reminder)
    {
        $reminder->delete();

        return redirect()->route('reminders.index')->with('status', 'Reminder deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'canned_job_id' => ['nullable', 'integer', 'exists:canned_jobs,id'],
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_on' => ['nullable', 'date'],
            'due_at_miles' => ['nullable', 'integer', 'min:0', 'max:9999999'],
        ]);

        // A reminder with neither target can never come due, which would sit in
        // the list forever pretending to be work.
        if (empty($data['due_on']) && empty($data['due_at_miles'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'due_on' => 'Give the reminder a date, an odometer target, or both.',
            ]);
        }

        return $data;
    }
}
