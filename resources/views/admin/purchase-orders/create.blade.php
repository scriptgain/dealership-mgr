<x-layouts.app title="New Purchase Order">
    <x-page-header title="New Purchase Order" icon="document"
        subtitle="Draft it, place it, then book deliveries in against it."
        :back="['href' => route('purchase-orders.index'), 'label' => 'Purchase Orders']" />

    @if ($shortages->count())
        <x-alert type="warning" title="Below Reorder Point" class="mb-6">
            {{ $shortages->take(8)->map(fn ($p) => $p->part_number.' ('.$p->onHand().')')->join(', ') }}{{ $shortages->count() > 8 ? ', and more' : '' }}
        </x-alert>
    @endif

    <form method="POST" action="{{ route('purchase-orders.store') }}"
          x-data="{ lines: [{ part: '{{ $preselect ?: '' }}', qty: 1, cost: '' }] }">
        @csrf
        <x-card title="Order">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="Supplier" required :error="$errors->first('supplier_id')">
                    <x-select name="supplier_id" required>
                        @foreach ($suppliers as $s)
                            <option value="{{ $s->id }}" @selected((int) old('supplier_id') === $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </x-select>
                </x-field>
                <x-field label="Expected On">
                    <x-input type="date" name="expected_on" value="{{ old('expected_on') }}" />
                </x-field>
                <x-field label="For Work Order" hint="Optional. Links the order to the job waiting on it.">
                    <x-select name="work_order_id">
                        <option value="">Stock order</option>
                        @foreach ($workOrders as $wo)
                            <option value="{{ $wo->id }}" @selected((int) old('work_order_id') === $wo->id)>{{ $wo->number }} - {{ $wo->title }}</option>
                        @endforeach
                    </x-select>
                </x-field>
                <x-field label="Supplier Reference">
                    <x-input name="supplier_reference" value="{{ old('supplier_reference') }}" />
                </x-field>
                <x-field label="Notes" class="sm:col-span-2">
                    <x-input name="notes" value="{{ old('notes') }}" />
                </x-field>
            </div>
        </x-card>

        <x-card class="mt-6" title="Lines" subtitle="Leave the cost blank to use the supplier's quoted price.">
            <template x-for="(line, index) in lines" :key="index">
                <div class="mb-3 flex flex-wrap items-end gap-3">
                    <div class="min-w-56 flex-1">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Part</label>
                        <select :name="`lines[${index}][part_id]`" x-model="line.part" required
                                class="block w-full appearance-none rounded-lg border-0 bg-white pl-3 pr-11 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                            <option value="">Choose a part</option>
                            @foreach ($parts as $part)
                                <option value="{{ $part->id }}">{{ $part->part_number }} - {{ $part->name }}{{ $part->brand ? ' ('.$part->brand.')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-24">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Qty</label>
                        <input type="number" min="1" :name="`lines[${index}][qty]`" x-model="line.qty" required
                               class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                    </div>
                    <div class="w-36">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Cost (cents)</label>
                        <input type="number" min="0" :name="`lines[${index}][unit_cost_cents]`" x-model="line.cost" placeholder="quoted"
                               class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                    </div>
                    <button type="button" x-show="lines.length > 1" @click="lines.splice(index, 1)"
                            class="rounded-lg border border-transparent px-3 py-2 text-sm font-medium text-rose-600 transition hover:border-rose-300 hover:bg-rose-50">Remove</button>
                </div>
            </template>
            <button type="button" @click="lines.push({ part: '', qty: 1, cost: '' })"
                    class="mt-2 rounded-lg border border-transparent px-3 py-2 text-sm font-semibold text-brand-700 transition hover:border-brand-300 hover:bg-brand-50">Add Another Line</button>
        </x-card>

        <div class="mt-6 flex justify-end gap-2">
            <x-button variant="secondary" href="{{ route('purchase-orders.index') }}">Cancel</x-button>
            <x-button type="submit" icon="check">Create Draft</x-button>
        </div>
    </form>
</x-layouts.app>
