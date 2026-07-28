<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inspection;
use App\Models\InspectionItem;
use App\Models\Product;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Digital vehicle inspections from the shop side: build the findings, attach
 * photos, send the link, then push what the customer approved onto the repair
 * order.
 */
class InspectionController extends Controller
{
    public function index(Request $request)
    {
        $inspections = Inspection::with(['vehicle', 'customer', 'technician'])
            ->withCount('items')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $like = '%'.$request->string('q').'%';
                $q->where('number', 'like', $like)
                    ->orWhereHas('vehicle', fn ($v) => $v->where('vin', 'like', $like)
                        ->orWhere('plate', 'like', $like)
                        ->orWhere('make', 'like', $like)
                        ->orWhere('model', 'like', $like));
            })
            ->latest()
            ->paginate((int) config('dealership.rows_per_page', 25))
            ->withQueryString();

        $filters = $request->only(['q', 'status']);

        return view('admin.inspections.index', [
            'inspections' => $inspections,
            'filters' => $filters,
            'tabs' => $this->indexTabs($filters),
        ]);
    }

    private function indexTabs(array $filters): array
    {
        $counts = ['all' => Inspection::count()]
            + collect(Inspection::STATUSES)
                ->mapWithKeys(fn ($s) => [$s => Inspection::where('status', $s)->count()])
                ->all();

        $current = $filters['status'] ?? 'all';

        return collect($counts)->map(fn ($count, $key) => [
            'label' => $key === 'all' ? 'All' : (new Inspection(['status' => $key]))->statusLabel(),
            'count' => $count,
            'active' => $current === $key || ($key === 'all' && ! in_array($current, Inspection::STATUSES, true)),
            'href' => route('inspections.index', array_filter([
                'q' => $filters['q'] ?? null,
                'status' => $key === 'all' ? null : $key,
            ])),
        ])->values()->all();
    }

    public function create(Request $request)
    {
        $vehicle = $request->filled('vehicle') ? Vehicle::find((int) $request->input('vehicle')) : null;
        $workOrder = $request->filled('work_order') ? WorkOrder::find((int) $request->input('work_order')) : null;

        return view('admin.inspections.create', [
            'inspection' => new Inspection([
                'vehicle_id' => $vehicle?->id ?? $workOrder?->vehicle_id,
                'customer_id' => $vehicle?->customer_id ?? $workOrder?->customer_id,
                'work_order_id' => $workOrder?->id,
                'mileage' => $vehicle?->currentMileage() ?? $workOrder?->vehicle?->currentMileage(),
                'status' => 'draft',
            ]),
            'vehicles' => Vehicle::with('customer')->where('is_active', true)->orderByDesc('updated_at')->get(),
            'services' => $this->serviceOptions(),
            'template' => $this->template(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $inspection = DB::transaction(function () use ($data, $request) {
            $vehicle = Vehicle::find($data['vehicle_id']);

            $inspection = Inspection::create([
                'number' => Inspection::nextNumber(),
                'vehicle_id' => $vehicle->id,
                'customer_id' => $vehicle->customer_id,
                'work_order_id' => $data['work_order_id'] ?? null,
                'performed_by_user_id' => $request->user()->id,
                'status' => 'draft',
                'mileage' => $data['mileage'] ?? null,
                'summary' => $data['summary'] ?? null,
            ]);

            $this->syncItems($inspection, $data['items'] ?? []);
            $inspection->recordActivity('created', 'Inspection started', [], $request->user()->id);

            return $inspection;
        });

        return redirect()->route('inspections.show', $inspection)->with('status', 'Inspection created.');
    }

    public function show(Inspection $inspection)
    {
        $inspection->load(['vehicle.customer', 'technician', 'workOrder', 'items.photos', 'items.product']);

        return view('admin.inspections.show', [
            'inspection' => $inspection,
            'counts' => $inspection->decisionCounts(),
        ]);
    }

    public function edit(Inspection $inspection)
    {
        $inspection->load(['items.photos']);

        return view('admin.inspections.edit', [
            'inspection' => $inspection,
            'vehicles' => Vehicle::with('customer')->where('is_active', true)->orderByDesc('updated_at')->get(),
            'services' => $this->serviceOptions(),
            'template' => $this->template(),
        ]);
    }

    public function update(Request $request, Inspection $inspection)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $inspection) {
            $inspection->update([
                'vehicle_id' => $data['vehicle_id'],
                'work_order_id' => $data['work_order_id'] ?? null,
                'mileage' => $data['mileage'] ?? null,
                'summary' => $data['summary'] ?? null,
            ]);

            $this->syncItems($inspection, $data['items'] ?? []);
        });

        return redirect()->route('inspections.show', $inspection)->with('status', 'Inspection updated.');
    }

    public function destroy(Inspection $inspection)
    {
        $inspection->delete();

        return redirect()->route('inspections.index')->with('status', 'Inspection deleted.');
    }

    /** Hand the inspection to the customer. */
    public function send(Request $request, Inspection $inspection)
    {
        if ($inspection->items()->count() === 0) {
            return back()->with('error', 'Add at least one finding before sending this to the customer.');
        }

        $inspection->update([
            'status' => 'sent',
            'sent_at' => $inspection->sent_at ?? now(),
        ]);

        $inspection->recordActivity('sent', 'Sent to customer for review', [], $request->user()->id);

        return back()->with('status', 'Inspection is ready for the customer. Copy the review link and send it to them.');
    }

    public function close(Request $request, Inspection $inspection)
    {
        $inspection->update(['status' => 'closed']);
        $inspection->recordActivity('closed', 'Inspection closed', [], $request->user()->id);

        return back()->with('status', 'Inspection closed.');
    }

    /**
     * Push the approved findings onto the repair order as billable lines. Lines
     * already pushed are skipped, so pressing this twice cannot double-bill.
     */
    public function bill(Request $request, Inspection $inspection)
    {
        $pending = $inspection->approvedNotYetBilled();

        if ($pending->isEmpty()) {
            return back()->with('error', 'There are no newly approved findings to add.');
        }

        $workOrder = $inspection->workOrder;

        if (! $workOrder) {
            $workOrder = WorkOrder::create([
                'number' => WorkOrder::nextNumber(),
                'customer_id' => $inspection->customer_id,
                'vehicle_id' => $inspection->vehicle_id,
                'mileage_in' => $inspection->mileage,
                'title' => 'Approved work from inspection '.$inspection->number,
                'status' => 'scheduled',
                'assigned_user_id' => $inspection->performed_by_user_id,
            ]);

            $inspection->update(['work_order_id' => $workOrder->id]);
        }

        DB::transaction(function () use ($pending, $workOrder, $inspection, $request) {
            foreach ($pending as $item) {
                $line = WorkOrderItem::create([
                    'work_order_id' => $workOrder->id,
                    'name' => $item->name,
                    'quantity' => 1,
                    'unit_price_cents' => (int) $item->price_cents,
                    'total_cents' => (int) $item->price_cents,
                ]);

                $item->update(['work_order_item_id' => $line->id]);
            }

            $workOrder->recalcTotals();

            $inspection->recordActivity(
                'billed',
                $pending->count().' approved '.\Illuminate\Support\Str::plural('finding', $pending->count()).' added to '.$workOrder->number,
                [],
                $request->user()->id
            );
        });

        return redirect()->route('work-orders.show', $workOrder)
            ->with('status', $pending->count().' approved '.\Illuminate\Support\Str::plural('finding', $pending->count()).' added to this repair order.');
    }

    /** Photos are what make a finding credible, so uploading is its own action. */
    public function uploadPhoto(Request $request, Inspection $inspection, InspectionItem $item)
    {
        abort_unless($item->inspection_id === $inspection->id, 404);

        $request->validate([
            'photos' => ['required', 'array', 'max:8'],
            'photos.*' => ['image', 'max:8192'],
        ], [], ['photos.*' => 'photo']);

        foreach ($request->file('photos') as $file) {
            if (! $file->isValid()) {
                continue;
            }

            $path = $file->store('inspections/'.$inspection->id, 'local');

            $item->photos()->create([
                'uploaded_by_user_id' => $request->user()->id,
                'disk' => 'local',
                'path' => $path,
                'filename' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'is_internal' => false,
            ]);
        }

        return back()->with('status', 'Photos added.');
    }

    public function destroyPhoto(Inspection $inspection, InspectionItem $item, \App\Models\Attachment $attachment)
    {
        abort_unless($item->inspection_id === $inspection->id, 404);
        abort_unless((int) $attachment->attachable_id === $item->id && $attachment->attachable_type === InspectionItem::class, 404);

        Storage::disk($attachment->disk ?: 'local')->delete($attachment->path);
        $attachment->delete();

        return back()->with('status', 'Photo removed.');
    }

    /**
     * Stream a photo to staff. The customer-facing equivalent lives on the public
     * controller and is scoped by the review token instead of a login.
     */
    public function photo(Inspection $inspection, \App\Models\Attachment $attachment)
    {
        abort_unless(
            $attachment->attachable_type === InspectionItem::class
            && $inspection->items->pluck('id')->contains((int) $attachment->attachable_id),
            404
        );

        $disk = Storage::disk($attachment->disk ?: 'local');
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response($attachment->path, $attachment->filename);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'work_order_id' => ['nullable', 'integer', 'exists:work_orders,id'],
            'mileage' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'items' => ['nullable', 'array'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.category' => ['nullable', 'string', 'max:120'],
            'items.*.finding' => ['nullable', 'string', 'max:2000'],
            'items.*.measurement' => ['nullable', 'string', 'max:64'],
            'items.*.severity' => ['nullable', Rule::in(array_keys(InspectionItem::SEVERITIES))],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.price' => ['nullable', 'string', 'max:32'],
            'items.*.id' => ['nullable', 'integer'],
        ]);
    }

    /**
     * Rebuild the findings. Existing rows are updated in place rather than
     * deleted and recreated, so a customer's decision and its photos survive an
     * edit by the shop.
     */
    private function syncItems(Inspection $inspection, array $rows): void
    {
        $keep = [];
        $position = 0;

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $payload = [
                'category' => $row['category'] ?? null,
                'name' => $name,
                'finding' => $row['finding'] ?? null,
                'measurement' => $row['measurement'] ?? null,
                'severity' => $row['severity'] ?? 'ok',
                'product_id' => $row['product_id'] ?? null,
                'price_cents' => $this->toCents($row['price'] ?? null),
                'position' => $position++,
            ];

            $existing = ! empty($row['id'])
                ? $inspection->items()->whereKey((int) $row['id'])->first()
                : null;

            if ($existing) {
                $existing->update($payload);
                $keep[] = $existing->id;
            } else {
                $keep[] = $inspection->items()->create($payload)->id;
            }
        }

        // Anything the shop removed from the form goes, along with its photos.
        $inspection->items()->whereNotIn('id', $keep ?: [0])->each(function (InspectionItem $item) {
            foreach ($item->photos as $photo) {
                Storage::disk($photo->disk ?: 'local')->delete($photo->path);
                $photo->delete();
            }
            $item->delete();
        });
    }

    private function toCents(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return (int) round((float) preg_replace('/[^0-9.\-]/', '', $value) * 100);
    }

    /** The service catalogue, for pricing a recommendation in one click. */
    private function serviceOptions()
    {
        return Product::with(['variants' => fn ($q) => $q->orderBy('position')])
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price_cents' => (int) ($p->variants->first()->price_cents ?? 0),
            ]);
    }

    /**
     * The standard walk-around. A technician starts from this rather than a blank
     * page, which is the difference between inspections getting done and not.
     */
    private function template(): array
    {
        return [
            'Brakes' => ['Front brake pads', 'Rear brake pads', 'Brake rotors', 'Brake fluid'],
            'Tires' => ['Front tire tread', 'Rear tire tread', 'Tire pressure', 'Spare tire'],
            'Under Hood' => ['Engine oil', 'Coolant', 'Battery and terminals', 'Drive belt', 'Air filter'],
            'Steering And Suspension' => ['Shocks and struts', 'Ball joints and bushings', 'Wheel alignment', 'Power steering'],
            'Lighting And Glass' => ['Headlights and indicators', 'Wiper blades', 'Windscreen'],
            'Underneath' => ['Exhaust system', 'Fluid leaks', 'CV boots and axles'],
        ];
    }
}
