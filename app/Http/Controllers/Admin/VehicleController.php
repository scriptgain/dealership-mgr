<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The vehicles a shop services. The profile page is the one screen a service
 * writer keeps open: who owns it, what it is, and everything done to it.
 */
class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $vehicles = Vehicle::with('customer')
            ->withCount('workOrders')
            ->search($request->string('q')->toString() ?: null)
            ->when($request->string('state')->toString() === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->string('state')->toString() === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('updated_at')
            ->paginate((int) config('dealership.rows_per_page', 25))
            ->withQueryString();

        $filters = $request->only(['q', 'state']);

        return view('admin.vehicles.index', [
            'vehicles' => $vehicles,
            'filters' => $filters,
            'tabs' => $this->indexTabs($filters),
        ]);
    }

    private function indexTabs(array $filters): array
    {
        $counts = [
            'all' => Vehicle::count(),
            'active' => Vehicle::where('is_active', true)->count(),
            'inactive' => Vehicle::where('is_active', false)->count(),
        ];

        $current = $filters['state'] ?? 'all';

        return collect($counts)->map(fn ($count, $key) => [
            'label' => $key === 'all' ? 'All' : ucfirst($key),
            'count' => $count,
            'active' => $current === $key || ($key === 'all' && ! in_array($current, ['active', 'inactive'], true)),
            'href' => route('vehicles.index', array_filter([
                'q' => $filters['q'] ?? null,
                'state' => $key === 'all' ? null : $key,
            ])),
        ])->values()->all();
    }

    public function create()
    {
        return view('admin.vehicles.create', [
            'vehicle' => new Vehicle(['is_active' => true]),
            'customers' => $this->customers(),
        ]);
    }

    public function store(Request $request)
    {
        $vehicle = Vehicle::create($this->validated($request));

        return redirect()
            ->route('vehicles.show', $vehicle)
            ->with('status', 'Vehicle added.');
    }

    public function show(Vehicle $vehicle)
    {
        $vehicle->load('customer');

        return view('admin.vehicles.show', [
            'vehicle' => $vehicle,
            // The service history: every visit, newest first, with what was done.
            'history' => $vehicle->workOrders()
                ->with('items')
                ->orderByRaw('COALESCE(completed_at, scheduled_at, created_at) DESC')
                ->paginate(15),
            'openRequests' => $vehicle->serviceRequests()->whereIn('status', ['new', 'reviewing'])->latest()->get(),
            'quotes' => $vehicle->quotes()->latest()->take(5)->get(),
            'milesPerDay' => $vehicle->milesPerDay(),
        ]);
    }

    public function edit(Vehicle $vehicle)
    {
        return view('admin.vehicles.edit', [
            'vehicle' => $vehicle,
            'customers' => $this->customers(),
        ]);
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $vehicle->update($this->validated($request, $vehicle));

        return redirect()
            ->route('vehicles.show', $vehicle)
            ->with('status', 'Vehicle updated.');
    }

    public function destroy(Vehicle $vehicle)
    {
        $vehicle->delete();

        return redirect()
            ->route('vehicles.index')
            ->with('status', 'Vehicle removed. Its work orders were kept and unlinked.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = collect($request->input('ids', []))->filter()->map(fn ($id) => (int) $id)->all();

        $count = $ids ? Vehicle::whereIn('id', $ids)->delete() : 0;

        return redirect()
            ->route('vehicles.index')
            ->with('status', $count.' '.\Illuminate\Support\Str::plural('vehicle', $count).' removed.');
    }

    private function validated(Request $request, ?Vehicle $vehicle = null): array
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 2)],
            'make' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'trim' => ['nullable', 'string', 'max:255'],
            'vin' => [
                'nullable', 'string', 'max:32',
                Rule::unique('vehicles', 'vin')->ignore($vehicle?->id),
            ],
            'plate' => ['nullable', 'string', 'max:32'],
            'plate_state' => ['nullable', 'string', 'max:8'],
            'color' => ['nullable', 'string', 'max:64'],
            'engine' => ['nullable', 'string', 'max:128'],
            'transmission' => ['nullable', 'string', 'max:64'],
            'drivetrain' => ['nullable', 'string', 'max:16'],
            'mileage' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'mileage_read_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ], [], [
            'vin' => 'VIN',
        ]);

        // A vehicle needs something to identify it by, or the list becomes a
        // wall of "Unidentified Vehicle" rows nobody can use.
        if (! array_filter([$data['make'] ?? null, $data['model'] ?? null, $data['vin'] ?? null, $data['plate'] ?? null])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'make' => 'Give the vehicle at least a make and model, a VIN, or a plate.',
            ]);
        }

        $data['vin'] = $data['vin'] ? strtoupper(trim($data['vin'])) : null;
        $data['plate'] = $data['plate'] ? strtoupper(trim($data['plate'])) : null;
        $data['is_active'] = $request->boolean('is_active');

        // Recording an odometer without a date makes the next-service estimate
        // meaningless, so default it to today rather than leaving it null.
        if (! empty($data['mileage']) && empty($data['mileage_read_on'])) {
            $data['mileage_read_on'] = now()->toDateString();
        }

        return $data;
    }

    private function customers()
    {
        return Customer::orderBy('last_name')->orderBy('first_name')->get();
    }
}
