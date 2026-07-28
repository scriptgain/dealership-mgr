@php
    // Seed the Alpine line-item repeater from old input (a failed submit) or the
    // work order's saved items, falling back to one empty row.
    $initialItems = old('items');
    if (! is_array($initialItems)) {
        $initialItems = $workOrder->exists && $workOrder->items->isNotEmpty()
            ? $workOrder->items->map(fn ($item) => [
                'service_id' => $item->service_id,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit_price' => number_format($item->unit_price_cents / 100, 2, '.', ''),
            ])->all()
            : [['service_id' => '', 'name' => '', 'quantity' => 1, 'unit_price' => '']];
    }
    $address = old('address', $workOrder->address ?? []);
    $scheduledValue = old('scheduled_at', $workOrder->scheduled_at?->format('Y-m-d\TH:i'));

    // Open on the step that owns the first validation error, so a failed submit
    // lands the user where the problem is instead of on step 1.
    $initialStep = 'details';
    foreach (array_keys($errors->getMessages()) as $k) {
        if (str_starts_with($k, 'items')) { $initialStep = 'items'; break; }
        if (str_starts_with($k, 'address') || in_array($k, ['customer_id', 'assigned_user_id', 'duration_minutes'], true)) { $initialStep = 'assign'; }
    }
@endphp

<form method="POST" action="{{ $workOrder->exists ? route('work-orders.update', $workOrder) : route('work-orders.store') }}"
    class="space-y-6" x-data="{ step: @js($initialStep), customerId: @js(old('customer_id', $workOrder->customer_id)) }">
    @csrf
    @if ($workOrder->exists) @method('PUT') @endif

    <x-segmented label="Work order steps">
        <button type="button" role="tab" :aria-selected="(step === 'details').toString()" @click="step = 'details'"
            class="vx-seg-item" :class="step === 'details' && 'is-active'">1 &middot; Details</button>
        <button type="button" role="tab" :aria-selected="(step === 'items').toString()" @click="step = 'items'"
            class="vx-seg-item" :class="step === 'items' && 'is-active'">2 &middot; Line Items</button>
        <button type="button" role="tab" :aria-selected="(step === 'assign').toString()" @click="step = 'assign'"
            class="vx-seg-item" :class="step === 'assign' && 'is-active'">3 &middot; Location &amp; Assignment</button>
    </x-segmented>

    {{-- STEP 1: DETAILS --}}
    <div x-show="step === 'details'" class="space-y-4">
        <x-card title="Details" subtitle="What the job is and where it stands.">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-field label="Title" for="title" required :error="$errors->first('title')" class="sm:col-span-2">
                    <x-input id="title" name="title" :value="old('title', $workOrder->title)" required autofocus />
                </x-field>
                <x-field label="Status" for="status" required :error="$errors->first('status')">
                    <x-select id="status" name="status">
                        @foreach (\App\Models\WorkOrder::STATUSES as $status)
                            <option value="{{ $status }}" @selected(old('status', $workOrder->status) === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                        @endforeach
                    </x-select>
                </x-field>
                <x-field label="Scheduled For" for="scheduled_at" :error="$errors->first('scheduled_at')">
                    <input type="datetime-local" id="scheduled_at" name="scheduled_at" value="{{ $scheduledValue }}"
                        class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                </x-field>
                <x-field label="Notes" for="notes" :error="$errors->first('notes')" class="sm:col-span-2">
                    <textarea id="notes" name="notes" rows="4"
                        class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('notes', $workOrder->notes) }}</textarea>
                </x-field>
            </div>
        </x-card>
        <div class="flex justify-end">
            <x-button type="button" variant="secondary" @click="step = 'items'">Continue To Line Items</x-button>
        </div>
    </div>

    {{-- STEP 2: LINE ITEMS --}}
    <div x-show="step === 'items'" x-cloak class="space-y-4">
        <x-card title="Line Items" subtitle="The services performed. Pick from the catalog or type a free line; totals are computed on save.">
            <div x-data="workOrderItems(@js($initialItems), @js($serviceOptions))" class="space-y-3">
                <template x-for="(row, index) in rows" :key="index">
                    <div class="rounded-lg p-4 ring-1 ring-slate-200">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-12">
                            <div class="space-y-1.5 sm:col-span-4">
                                <label class="block text-xs font-medium text-slate-500" :for="`item-${index}-service`">Service</label>
                                <div class="relative">
                                    <select :id="`item-${index}-service`" :name="`items[${index}][service_id]`" x-model="row.service_id" @change="applyService(row)"
                                        class="block w-full appearance-none rounded-lg border-0 bg-white py-2 pl-3 pr-9 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                        <option value="">Free Text</option>
                                        <template x-for="service in services" :key="service.id">
                                            <option :value="service.id" x-text="service.name"></option>
                                        </template>
                                    </select>
                                    <x-icon name="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                </div>
                            </div>
                            <div class="space-y-1.5 sm:col-span-4">
                                <label class="block text-xs font-medium text-slate-500" :for="`item-${index}-name`">Description</label>
                                <input type="text" :id="`item-${index}-name`" :name="`items[${index}][name]`" x-model="row.name" placeholder="What was done"
                                    class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                            </div>
                            <div class="space-y-1.5 sm:col-span-1">
                                <label class="block text-xs font-medium text-slate-500" :for="`item-${index}-qty`">Qty</label>
                                <input type="number" min="1" :id="`item-${index}-qty`" :name="`items[${index}][quantity]`" x-model="row.quantity"
                                    class="tabular block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                            </div>
                            <div class="space-y-1.5 sm:col-span-2">
                                <label class="block text-xs font-medium text-slate-500" :for="`item-${index}-price`">Unit Price</label>
                                <input type="text" inputmode="decimal" :id="`item-${index}-price`" :name="`items[${index}][unit_price]`" x-model="row.unit_price" placeholder="0.00"
                                    class="tabular block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                            </div>
                            <div class="flex items-end justify-end sm:col-span-1">
                                <button type="button" x-show="rows.length > 1" @click="removeRow(index)"
                                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-rose-600 ring-1 ring-inset ring-rose-200 transition hover:bg-rose-50"
                                    aria-label="Remove line item">
                                    <x-icon name="trash" class="h-4 w-4" aria-hidden="true" />
                                </button>
                            </div>
                        </div>
                        <p class="mt-2 text-right text-xs text-slate-500">Line total <span class="font-medium text-slate-700" x-text="lineTotal(row)"></span></p>
                    </div>
                </template>

                <div class="flex items-center justify-between">
                    <x-button type="button" variant="secondary" icon="plus" @click="addRow()">Add Line Item</x-button>
                    <p class="text-sm text-slate-600">Subtotal <span class="tabular font-semibold text-slate-900" x-text="subtotal()"></span></p>
                </div>
            </div>
        </x-card>
        <div class="flex justify-between">
            <x-button type="button" variant="secondary" @click="step = 'details'">Back</x-button>
            <x-button type="button" variant="secondary" @click="step = 'assign'">Continue To Assignment</x-button>
        </div>
    </div>

    {{-- STEP 3: LOCATION & ASSIGNMENT --}}
    <div x-show="step === 'assign'" x-cloak class="space-y-4">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-card title="Service Address" subtitle="Where the work happens.">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-field label="Address Line 1" for="address_line1" class="sm:col-span-2">
                        <x-input id="address_line1" name="address[line1]" :value="$address['line1'] ?? ''" />
                    </x-field>
                    <x-field label="Address Line 2" for="address_line2" class="sm:col-span-2">
                        <x-input id="address_line2" name="address[line2]" :value="$address['line2'] ?? ''" />
                    </x-field>
                    <x-field label="City" for="address_city">
                        <x-input id="address_city" name="address[city]" :value="$address['city'] ?? ''" />
                    </x-field>
                    <x-field label="State" for="address_state">
                        <x-input id="address_state" name="address[state]" :value="$address['state'] ?? ''" />
                    </x-field>
                    <x-field label="Postcode" for="address_postcode" class="sm:col-span-2">
                        <x-input id="address_postcode" name="address[postcode]" :value="$address['postcode'] ?? ''" />
                    </x-field>
                </div>
            </x-card>

            <x-card title="Assignment">
                <div class="space-y-5">
                    <x-field label="Customer" for="customer_id" :error="$errors->first('customer_id')">
                        <x-select id="customer_id" name="customer_id" x-model="customerId">
                            <option value="">No Customer</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" @selected((int) old('customer_id', $workOrder->customer_id) === $customer->id)>{{ $customer->name }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <x-field label="Vehicle" for="vehicle_id" :error="$errors->first('vehicle_id')"
                        hint="Picking a customer narrows this list to their vehicles.">
                        <x-select id="vehicle_id" name="vehicle_id">
                            <option value="">No Vehicle</option>
                            @foreach ($vehicles as $vehicle)
                                <option value="{{ $vehicle['id'] }}"
                                    data-customer="{{ $vehicle['customer_id'] }}"
                                    x-show="! customerId || String(customerId) === '{{ $vehicle['customer_id'] }}'"
                                    @selected((int) old('vehicle_id', $workOrder->vehicle_id) === $vehicle['id'])>{{ $vehicle['label'] }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <div class="grid grid-cols-2 gap-4">
                        <x-field label="Odometer In" for="mileage_in" :error="$errors->first('mileage_in')">
                            <x-input id="mileage_in" name="mileage_in" type="number" min="0"
                                :value="old('mileage_in', $workOrder->mileage_in)" placeholder="84500" />
                        </x-field>
                        <x-field label="Odometer Out" for="mileage_out" :error="$errors->first('mileage_out')">
                            <x-input id="mileage_out" name="mileage_out" type="number" min="0"
                                :value="old('mileage_out', $workOrder->mileage_out)" placeholder="84512" />
                        </x-field>
                    </div>
                    <x-field label="Assigned Agent" for="assigned_user_id" :error="$errors->first('assigned_user_id')">
                        <x-select id="assigned_user_id" name="assigned_user_id">
                            <option value="">Unassigned</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->id }}" @selected((int) old('assigned_user_id', $workOrder->assigned_user_id) === $agent->id)>{{ $agent->name }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <x-field label="Duration (Minutes)" for="duration_minutes" hint="Optional. Estimated time on site." :error="$errors->first('duration_minutes')">
                        <x-input id="duration_minutes" name="duration_minutes" type="number" min="0" :value="old('duration_minutes', $workOrder->duration_minutes)" />
                    </x-field>
                </div>
            </x-card>
        </div>
        <div class="flex justify-start">
            <x-button type="button" variant="secondary" @click="step = 'items'">Back</x-button>
        </div>
    </div>

    <div class="sticky bottom-0 z-20 -mx-4 flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3 shadow-[0_-4px_12px_-6px_rgba(15,23,42,0.15)] sm:-mx-6 sm:px-6">
        <x-button variant="secondary" href="{{ $workOrder->exists ? route('work-orders.show', $workOrder) : route('work-orders.index') }}">Cancel</x-button>
        <x-button type="submit" icon="check">{{ $workOrder->exists ? 'Save Changes' : 'Create Work Order' }}</x-button>
    </div>
</form>

{{-- Registered on alpine:init (mirrors how dealership-admin.js registers its own
     repeaters) since this project has no scripts stack and the shared JS file
     is not edited per-module. --}}
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('workOrderItems', (initial, services) => ({
            rows: initial.length ? initial.map(r => ({
                service_id: r.service_id ?? '',
                name: r.name ?? '',
                quantity: r.quantity ?? 1,
                unit_price: r.unit_price ?? '',
            })) : [{ service_id: '', name: '', quantity: 1, unit_price: '' }],
            services: services,
            addRow() {
                this.rows.push({ service_id: '', name: '', quantity: 1, unit_price: '' });
            },
            removeRow(index) {
                this.rows.splice(index, 1);
                if (! this.rows.length) this.addRow();
            },
            applyService(row) {
                const svc = this.services.find(s => String(s.id) === String(row.service_id));
                if (svc) {
                    if (! row.name) row.name = svc.name;
                    if (! row.unit_price || row.unit_price === '0.00') row.unit_price = svc.price;
                }
            },
            money(cents) {
                return '{{ config('dealership.currency_symbol', '$') }}' + (cents / 100).toFixed(2);
            },
            lineCents(row) {
                const qty = parseInt(row.quantity) || 0;
                const unit = Math.round((parseFloat(String(row.unit_price).replace(/[^0-9.\-]/g, '')) || 0) * 100);
                return qty * unit;
            },
            lineTotal(row) {
                return this.money(this.lineCents(row));
            },
            subtotal() {
                return this.money(this.rows.reduce((sum, row) => sum + this.lineCents(row), 0));
            },
        }));
    });
</script>
