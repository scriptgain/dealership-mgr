@php
    $initialItems = old('items', $inspection->exists
        ? $inspection->items->map(fn ($i) => [
            'id' => $i->id,
            'category' => $i->category,
            'name' => $i->name,
            'finding' => $i->finding,
            'measurement' => $i->measurement,
            'severity' => $i->severity,
            'product_id' => $i->product_id,
            'price' => $i->price_cents !== null ? number_format($i->price_cents / 100, 2, '.', '') : '',
        ])->values()->all()
        : []);
@endphp

<form method="POST" action="{{ $inspection->exists ? route('inspections.update', $inspection) : route('inspections.store') }}" class="space-y-6">
    @csrf
    @if ($inspection->exists) @method('PUT') @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-card title="Findings" subtitle="What you checked, what you found, and what it costs to put right." flush>
                <div x-data="inspectionItems(@js($initialItems), @js($services), @js($template))" class="divide-y divide-slate-200">

                    <div class="flex flex-wrap items-center gap-2 px-4 py-3 sm:px-6">
                        <x-button type="button" variant="secondary" size="sm" icon="plus" x-on:click="addBlank()">Add Finding</x-button>
                        <div class="ml-auto flex items-center gap-2">
                            <label for="tpl" class="text-xs font-medium text-slate-600">Load Checklist</label>
                            <select id="tpl" x-model="tplGroup"
                                class="rounded-lg border-0 bg-white py-1.5 pl-2 pr-8 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                <option value="">Choose a group</option>
                                <template x-for="(names, group) in template" :key="group">
                                    <option :value="group" x-text="group"></option>
                                </template>
                            </select>
                            <x-button type="button" variant="secondary" size="sm" x-on:click="loadTemplate()">Add</x-button>
                        </div>
                    </div>

                    <template x-for="(row, index) in rows" :key="index">
                        <div class="px-4 py-4 sm:px-6">
                            <input type="hidden" :name="`items[${index}][id]`" :value="row.id || ''">

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-12">
                                <div class="sm:col-span-3">
                                    <label class="sr-only">Category</label>
                                    <input type="text" :name="`items[${index}][category]`" x-model="row.category" placeholder="Brakes"
                                        class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                </div>
                                <div class="sm:col-span-5">
                                    <label class="sr-only">Finding</label>
                                    <input type="text" :name="`items[${index}][name]`" x-model="row.name" placeholder="Front brake pads"
                                        class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="sr-only">Measurement</label>
                                    <input type="text" :name="`items[${index}][measurement]`" x-model="row.measurement" placeholder="3mm"
                                        class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="sr-only">Severity</label>
                                    <select :name="`items[${index}][severity]`" x-model="row.severity"
                                        class="block w-full rounded-lg border-0 bg-white py-2 pl-3 pr-8 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                        @foreach (\App\Models\InspectionItem::SEVERITIES as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="sm:col-span-7">
                                    <label class="sr-only">Notes</label>
                                    <textarea :name="`items[${index}][finding]`" x-model="row.finding" rows="2"
                                        placeholder="Worn to the wear indicator, scoring on the rotor face."
                                        class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500"></textarea>
                                </div>
                                <div class="sm:col-span-3">
                                    <label class="sr-only">Recommended Service</label>
                                    <select :name="`items[${index}][product_id]`" x-model="row.product_id" x-on:change="priceFromService(index)"
                                        class="block w-full rounded-lg border-0 bg-white py-2 pl-3 pr-8 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                        <option value="">No service</option>
                                        <template x-for="svc in services" :key="svc.id">
                                            <option :value="svc.id" x-text="svc.name"></option>
                                        </template>
                                    </select>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="sr-only">Price</label>
                                    <input type="text" :name="`items[${index}][price]`" x-model="row.price" placeholder="419.00" inputmode="decimal"
                                        class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm tabular-nums text-slate-900 ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                </div>
                            </div>

                            <div class="mt-2 flex items-center justify-between gap-3">
                                <p class="text-xs text-slate-500" x-show="row.id">
                                    Photos are added from the inspection page once this is saved.
                                </p>
                                <button type="button" x-on:click="remove(index)"
                                    class="rounded-lg px-2 py-1 text-xs font-medium text-rose-600 transition hover:bg-rose-50 hover:ring-1 hover:ring-inset hover:ring-rose-200">
                                    Remove
                                </button>
                            </div>
                        </div>
                    </template>

                    <div x-show="! rows.length" class="px-4 py-10 text-center sm:px-6">
                        <p class="text-sm text-slate-500">No findings yet. Load a checklist group or add one by hand.</p>
                    </div>
                </div>
            </x-card>

            <x-card title="Summary" subtitle="A sentence the customer reads first. Optional.">
                <x-field label="Summary" for="summary" :error="$errors->first('summary')">
                    <textarea id="summary" name="summary" rows="3"
                        class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500"
                        placeholder="Brakes need doing now. Tires have a season left. Everything else looked good.">{{ old('summary', $inspection->summary) }}</textarea>
                </x-field>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Vehicle">
                <div class="space-y-5">
                    <x-field label="Vehicle" for="vehicle_id" required :error="$errors->first('vehicle_id')">
                        <x-select id="vehicle_id" name="vehicle_id" required>
                            <option value="">Choose a vehicle</option>
                            @foreach ($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}" @selected((int) old('vehicle_id', $inspection->vehicle_id) === $vehicle->id)>
                                    {{ $vehicle->name }}{{ $vehicle->plate ? ' ('.strtoupper($vehicle->plate).')' : '' }}{{ $vehicle->customer ? ' - '.$vehicle->customer->name : '' }}
                                </option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <x-field label="Odometer" for="mileage" :error="$errors->first('mileage')">
                        <x-input id="mileage" name="mileage" type="number" min="0" :value="old('mileage', $inspection->mileage)" />
                    </x-field>
                    @if ($inspection->work_order_id || old('work_order_id'))
                        <input type="hidden" name="work_order_id" value="{{ old('work_order_id', $inspection->work_order_id) }}">
                        <p class="text-xs text-slate-500">Linked to repair order {{ $inspection->workOrder?->number }}.</p>
                    @endif
                </div>
            </x-card>

            <x-card title="How This Works">
                <ol class="space-y-2 text-sm text-slate-600">
                    <li>1. Record what you found, with a price on anything that needs doing.</li>
                    <li>2. Save, then add photos to each finding.</li>
                    <li>3. Send it. The customer approves or declines each line on their phone.</li>
                    <li>4. Push what they approved onto the repair order.</li>
                </ol>
            </x-card>
        </div>
    </div>

    <div class="sticky bottom-0 z-20 -mx-4 flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3 shadow-[0_-4px_12px_-6px_rgba(15,23,42,0.15)] sm:-mx-6 sm:px-6">
        <x-button variant="secondary" href="{{ $inspection->exists ? route('inspections.show', $inspection) : route('inspections.index') }}">Cancel</x-button>
        <x-button type="submit" icon="check">{{ $inspection->exists ? 'Save Inspection' : 'Create Inspection' }}</x-button>
    </div>
</form>

{{-- Inline, matching admin/work-orders/_form.blade.php: the layouts have no
     scripts stack, so a push directive here would render nothing at all. --}}
<script>
    // Registered on alpine:init, mirroring how the repair-order line editor works.
    document.addEventListener('alpine:init', () => {
        Alpine.data('inspectionItems', (initial, services, template) => ({
            rows: initial && initial.length ? JSON.parse(JSON.stringify(initial)) : [],
            services: services || [],
            template: template || {},
            tplGroup: '',

            addBlank() {
                this.rows.push({ id: null, category: '', name: '', finding: '', measurement: '', severity: 'ok', product_id: '', price: '' });
            },

            // Loading a checklist adds the group's standard checks as OK rows, so a
            // technician only edits the ones that are not.
            loadTemplate() {
                if (! this.tplGroup || ! this.template[this.tplGroup]) return;
                this.template[this.tplGroup].forEach(name => {
                    this.rows.push({ id: null, category: this.tplGroup, name, finding: '', measurement: '', severity: 'ok', product_id: '', price: '' });
                });
                this.tplGroup = '';
            },

            priceFromService(index) {
                const row = this.rows[index];
                const svc = this.services.find(s => String(s.id) === String(row.product_id));
                if (svc && ! row.price) {
                    row.price = (svc.price_cents / 100).toFixed(2);
                }
                if (svc && ! row.name) {
                    row.name = svc.name;
                }
            },

            remove(index) {
                this.rows.splice(index, 1);
            },
        }));
    });
</script>
