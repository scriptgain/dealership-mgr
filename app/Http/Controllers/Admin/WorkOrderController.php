<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Scheduled service work. Full CRUD plus the workflow: change status, complete
 * (which can generate an invoice), and cancel with a reason. Line items freeze
 * the service name and price at scheduling time.
 */
class WorkOrderController extends Controller
{
    public function index(Request $request)
    {
        $workOrders = WorkOrder::with(['customer', 'assignee', 'bookingType'])
            ->search($request->string('q')->toString() ?: null)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->boolean('booking'), fn ($q) => $q->whereNotNull('booking_type_id'))
            ->when(
                $request->boolean('upcoming'),
                fn ($q) => $q->upcoming(),
                fn ($q) => $q->latest()
            )
            ->paginate((int) config('dealership.rows_per_page', 25))
            ->withQueryString();

        $filters = $request->only(['q', 'status', 'upcoming', 'booking']);

        return view('admin.work-orders.index', [
            'workOrders' => $workOrders,
            'filters' => $filters,
            'tabs' => $this->indexTabs($filters),
        ]);
    }

    private function indexTabs(array $filters): array
    {
        $counts = [
            'all' => WorkOrder::count(),
            'scheduled' => WorkOrder::where('status', 'scheduled')->count(),
            'in_progress' => WorkOrder::where('status', 'in_progress')->count(),
            'on_hold' => WorkOrder::where('status', 'on_hold')->count(),
            'completed' => WorkOrder::where('status', 'completed')->count(),
            'cancelled' => WorkOrder::where('status', 'cancelled')->count(),
        ];

        $definitions = [
            ['key' => 'all', 'label' => 'All', 'status' => null],
            ['key' => 'scheduled', 'label' => 'Scheduled', 'status' => 'scheduled'],
            ['key' => 'in_progress', 'label' => 'In Progress', 'status' => 'in_progress'],
            ['key' => 'on_hold', 'label' => 'On Hold', 'status' => 'on_hold'],
            ['key' => 'completed', 'label' => 'Completed', 'status' => 'completed'],
            ['key' => 'cancelled', 'label' => 'Cancelled', 'status' => 'cancelled'],
        ];

        $active = $filters['status'] ?? null;
        $preserve = array_filter([
            'q' => $filters['q'] ?? null,
            'upcoming' => ! empty($filters['upcoming']) ? 1 : null,
        ]);

        return array_map(fn ($tab) => [
            'label' => $tab['label'],
            'count' => $counts[$tab['key']] ?? 0,
            'active' => $tab['status'] === $active,
            'href' => route('work-orders.index', array_merge(
                $tab['status'] ? ['status' => $tab['status']] : [],
                $preserve
            )),
        ], $definitions);
    }

    public function create(Request $request)
    {
        // Arriving from a vehicle profile preselects that vehicle and its owner,
        // so "New Repair Order" from a vehicle does not make you pick it again.
        $preset = $request->filled('vehicle')
            ? Vehicle::find((int) $request->input('vehicle'))
            : null;

        return view('admin.work-orders.create', [
            'workOrder' => new WorkOrder([
                'status' => 'scheduled',
                'vehicle_id' => $preset?->id,
                'customer_id' => $preset?->customer_id,
            ]),
            'vehicles' => $this->vehicleOptions(),
            'customers' => Customer::orderBy('first_name')->orderBy('last_name')->get(),
            'agents' => User::orderBy('name')->get(),
            'serviceOptions' => $this->serviceOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateWorkOrder($request);

        $workOrder = DB::transaction(function () use ($data, $request) {
            $workOrder = WorkOrder::create([
                'customer_id' => $data['customer_id'] ?? null,
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'mileage_in' => $data['mileage_in'] ?? null,
                'mileage_out' => $data['mileage_out'] ?? null,
                'assigned_user_id' => $data['assigned_user_id'] ?? null,
                'title' => $data['title'],
                'notes' => $data['notes'] ?? null,
                'status' => $data['status'],
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'address' => $this->addressFrom($request),
                'currency' => config('dealership.currency', 'USD'),
            ]);

            $this->syncItems($workOrder, $request->input('items', []));
            $workOrder->recordActivity('created', 'Work Order Created');

            return $workOrder;
        });

        return redirect()
            ->route('work-orders.show', $workOrder)
            ->with('status', 'Work order '.$workOrder->number.' created.');
    }

    public function show(WorkOrder $workOrder)
    {
        $workOrder->load(['customer', 'assignee', 'ticket', 'project', 'items.service', 'items.part',
            'invoice', 'activities.user', 'timeEntries.user']);

        return view('admin.work-orders.show', [
            'workOrder' => $workOrder,
            'addressLines' => $this->addressLines($workOrder->address),
            'parts' => \App\Models\Part::active()->orderBy('part_number')->get(),
        ]);
    }

    public function edit(WorkOrder $workOrder)
    {
        $workOrder->load('items');

        return view('admin.work-orders.edit', [
            'workOrder' => $workOrder,
            'vehicles' => $this->vehicleOptions(),
            'customers' => Customer::orderBy('first_name')->orderBy('last_name')->get(),
            'agents' => User::orderBy('name')->get(),
            'serviceOptions' => $this->serviceOptions(),
        ]);
    }

    public function update(Request $request, WorkOrder $workOrder)
    {
        $data = $this->validateWorkOrder($request);

        DB::transaction(function () use ($data, $request, $workOrder) {
            $workOrder->forceFill([
                'customer_id' => $data['customer_id'] ?? null,
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'mileage_in' => $data['mileage_in'] ?? null,
                'mileage_out' => $data['mileage_out'] ?? null,
                'assigned_user_id' => $data['assigned_user_id'] ?? null,
                'title' => $data['title'],
                'notes' => $data['notes'] ?? null,
                'status' => $data['status'],
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'address' => $this->addressFrom($request),
            ])->save();

            $this->syncItems($workOrder, $request->input('items', []));
            $workOrder->recordActivity('note', 'Work Order Updated');
        });

        return redirect()
            ->route('work-orders.show', $workOrder)
            ->with('status', 'Work order updated.');
    }

    /**
     * Put a part on a job.
     *
     * Two things happen together or neither happens: a line appears on the
     * repair order at the selling price, and the stock comes off the shelf
     * through the ledger. A shop where those can drift apart bills for parts it
     * still has and loses parts it already sold.
     */
    public function issuePart(Request $request, WorkOrder $workOrder)
    {
        $data = $request->validate([
            'part_id' => ['required', 'integer', 'exists:parts,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        $part = \App\Models\Part::findOrFail($data['part_id']);

        if ($part->is_stocked && $part->onHand() < $data['quantity']) {
            return back()->withErrors([
                'part_id' => $part->part_number.' has '.$part->onHand()
                    .' on hand and cannot issue '.$data['quantity'].'. Order it, or adjust the count if the shelf disagrees.',
            ]);
        }

        DB::transaction(function () use ($workOrder, $part, $data) {
            $price = $data['unit_price_cents'] ?? $part->price_cents;

            $item = $workOrder->items()->create([
                'part_id' => $part->id,
                'line_type' => 'part',
                // Frozen at issue: a price rise next month must not silently
                // restate a repair order already handed to a customer.
                'name' => $part->part_number.' '.$part->name,
                'description' => $part->brand,
                'quantity' => $data['quantity'],
                'unit_price_cents' => $price,
                'unit_cost_cents' => $part->bestCostCents(),
                'total_cents' => $price * $data['quantity'],
            ]);

            if ($part->is_stocked) {
                $part->move('issued', -abs($data['quantity']), [
                    'work_order_id' => $workOrder->id,
                    'work_order_item_id' => $item->id,
                    'unit_cost_cents' => $part->bestCostCents(),
                    'reason' => 'Issued to '.$workOrder->number,
                ]);
            }

            $workOrder->recalcTotals();
            $workOrder->recordActivity('note', 'Part Issued: '.$part->part_number);
        });

        return back()->with('status', $part->part_number.' issued to '.$workOrder->number.'.');
    }

    /** Taking a part back off a job returns it to the shelf, by the same route. */
    public function returnPart(Request $request, WorkOrder $workOrder, \App\Models\WorkOrderItem $item)
    {
        if ($item->work_order_id !== $workOrder->id) {
            return back()->withErrors(['item' => 'That line is not on this work order.']);
        }

        if (! $item->part_id) {
            return back()->withErrors(['item' => 'That line is not a part.']);
        }

        DB::transaction(function () use ($workOrder, $item) {
            $part = $item->part;

            if ($part && $part->is_stocked) {
                $part->move('returned', abs((int) $item->quantity), [
                    'work_order_id' => $workOrder->id,
                    'unit_cost_cents' => $item->unit_cost_cents,
                    'reason' => 'Returned from '.$workOrder->number,
                ]);
            }

            $item->delete();
            $workOrder->recalcTotals();
            $workOrder->recordActivity('note', 'Part Returned To Stock');
        });

        return back()->with('status', 'Part returned to stock.');
    }

    public function status(Request $request, WorkOrder $workOrder)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(WorkOrder::STATUSES)],
        ]);

        DB::transaction(function () use ($workOrder, $data) {
            $attrs = ['status' => $data['status']];

            if ($data['status'] === 'in_progress' && ! $workOrder->started_at) {
                $attrs['started_at'] = now();
            }

            $workOrder->forceFill($attrs)->save();
            $workOrder->recordActivity('status', 'Status Changed To '.$workOrder->status_label);
        });

        return back()->with('status', 'Work order status updated.');
    }

    /**
     * Mark the work order completed. If it has a billable subtotal, generate an
     * invoice (an Order) from its line items and link the two together.
     */
    public function complete(WorkOrder $workOrder)
    {
        if ($workOrder->status === 'completed') {
            return back()->with('warning', 'This work order is already completed.');
        }

        $invoice = DB::transaction(function () use ($workOrder) {
            $workOrder->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
            ])->save();

            $workOrder->recordActivity('completed', 'Work Order Completed');

            $workOrder->recalcTotals();
            $workOrder->refresh();

            if ($workOrder->subtotal_cents <= 0) {
                return null;
            }

            $invoice = \App\Services\ServiceDesk\InvoiceBuilder::generate(
                $workOrder,
                array_filter([
                    'work_order_id' => $workOrder->id,
                    'project_id' => $workOrder->project_id,
                ]),
                'Work Order '.$workOrder->number
            );

            $workOrder->forceFill(['invoice_order_id' => $invoice->id])->save();

            $workOrder->recordActivity('payment', 'Invoice '.$invoice->number.' Generated', ['order_id' => $invoice->id]);

            return $invoice;
        });

        if ($invoice) {
            return back()->with('status', 'Work order completed. Invoice '.$invoice->number.' generated.');
        }

        return back()->with('status', 'Work order completed.');
    }

    public function cancel(Request $request, WorkOrder $workOrder)
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($workOrder, $data) {
            $workOrder->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $data['reason'] ?? null,
            ])->save();

            $workOrder->recordActivity(
                'cancelled',
                'Work Order Cancelled'.(! empty($data['reason']) ? ': '.$data['reason'] : '')
            );
        });

        return back()->with('status', 'Work order cancelled.');
    }

    public function destroy(WorkOrder $workOrder)
    {
        $workOrder->delete();

        return redirect()->route('work-orders.index')->with('status', 'Work order deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = collect($request->input('ids', []))->filter()->all();
        $count = WorkOrder::whereIn('id', $ids)->delete();

        return back()->with('status', "Deleted {$count} work order(s).");
    }

    /* ---- Helpers ------------------------------------------------------- */

    private function validateWorkOrder(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'mileage_in' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'mileage_out' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(WorkOrder::STATUSES)],
            'scheduled_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'address' => ['nullable', 'array'],
            'items' => ['nullable', 'array'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.service_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'string', 'max:32'],
        ]);
    }

    /**
     * Vehicles for the picker, labelled the way a service writer reads them out:
     * the vehicle, then the plate, then who owns it.
     */
    private function vehicleOptions()
    {
        return Vehicle::with('customer')
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Vehicle $v) => [
                'id' => $v->id,
                'customer_id' => $v->customer_id,
                'label' => trim($v->name.($v->plate ? ' ('.strtoupper($v->plate).')' : '')
                    .($v->customer ? ' - '.$v->customer->name : '')),
            ]);
    }

    /** Rebuild the line items, computing each total in integer cents. */
    private function syncItems(WorkOrder $workOrder, array $rows): void
    {
        $workOrder->items()->delete();

        foreach ($rows as $row) {
            $serviceId = ! empty($row['service_id']) ? (int) $row['service_id'] : null;
            $name = trim((string) ($row['name'] ?? ''));

            // A service picked without a typed name inherits the catalog name.
            if ($name === '' && $serviceId) {
                $name = Product::find($serviceId)?->name ?? '';
            }

            if ($name === '') {
                continue;
            }

            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $unit = Money::parse($row['unit_price'] ?? null) ?? 0;

            $workOrder->items()->create([
                'service_id' => $serviceId,
                'name' => $name,
                'quantity' => $qty,
                'unit_price_cents' => $unit,
                'total_cents' => $unit * $qty,
            ]);
        }

        $workOrder->recalcTotals();
    }

    /** Service catalog shaped for the line-item picker. */
    private function serviceOptions(): array
    {
        return Product::with('variants')->orderBy('name')->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => number_format($p->price_from_cents / 100, 2, '.', ''),
            ])
            ->values()
            ->all();
    }

    private function addressFrom(Request $request): ?array
    {
        $address = array_filter([
            'line1' => $request->input('address.line1'),
            'line2' => $request->input('address.line2'),
            'city' => $request->input('address.city'),
            'state' => $request->input('address.state'),
            'postcode' => $request->input('address.postcode'),
        ], fn ($v) => filled($v));

        return $address ?: null;
    }

    private function addressLines(?array $address): array
    {
        if (! $address) {
            return [];
        }

        $city = trim(
            ($address['city'] ?? '')
            .(! empty($address['state']) ? ', '.$address['state'] : '')
            .' '.($address['postcode'] ?? '')
        );

        return array_values(array_filter([
            $address['line1'] ?? null,
            $address['line2'] ?? null,
            $city,
        ]));
    }
}
