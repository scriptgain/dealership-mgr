<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <x-card title="Identity">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="Part Number" required :error="$errors->first('part_number')">
                    <x-input name="part_number" value="{{ old('part_number', $part->part_number) }}" required />
                </x-field>
                <x-field label="Brand" hint="Part number and brand together must be unique.">
                    <x-input name="brand" value="{{ old('brand', $part->brand) }}" />
                </x-field>
                <x-field label="Name" class="sm:col-span-2" required :error="$errors->first('name')">
                    <x-input name="name" value="{{ old('name', $part->name) }}" required />
                </x-field>
                <x-field label="Category">
                    <x-input name="category" value="{{ old('category', $part->category) }}" placeholder="Brakes" />
                </x-field>
                <x-field label="Bin Location">
                    <x-input name="bin_location" value="{{ old('bin_location', $part->bin_location) }}" placeholder="B-14-3" />
                </x-field>
                <x-field label="Description" class="sm:col-span-2">
                    <textarea name="description" rows="3"
                        class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('description', $part->description) }}</textarea>
                </x-field>
            </div>
        </x-card>

        <x-card title="Suppliers" subtitle="The same part comes from several factors at several prices. Mark the one to ring first.">
            @if ($suppliers->isEmpty())
                <p class="text-sm text-slate-500">
                    No suppliers on file yet. <a href="{{ route('suppliers.create') }}" class="font-semibold text-brand-700 hover:underline">Add one</a>.
                </p>
            @else
                <div class="space-y-3">
                    @foreach ($suppliers as $supplier)
                        @php($link = $links->get($supplier->id))
                        <div class="rounded-lg border border-slate-200 p-3 transition hover:border-brand-300">
                            <label class="flex items-center gap-3 text-sm font-medium text-slate-800">
                                <input type="checkbox" name="suppliers[{{ $supplier->id }}][selected]" value="1"
                                       @checked($link) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                {{ $supplier->name }}
                            </label>
                            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <x-field label="Their Number">
                                    <x-input name="suppliers[{{ $supplier->id }}][supplier_part_number]"
                                             value="{{ $link?->pivot->supplier_part_number }}" />
                                </x-field>
                                <x-field label="Cost (cents)">
                                    <x-input type="number" min="0" name="suppliers[{{ $supplier->id }}][cost_cents]"
                                             value="{{ $link?->pivot->cost_cents }}" />
                                </x-field>
                                <x-field label="Lead Days">
                                    <x-input type="number" min="0" name="suppliers[{{ $supplier->id }}][lead_time_days]"
                                             value="{{ $link?->pivot->lead_time_days ?? $supplier->lead_time_days }}" />
                                </x-field>
                                <x-field label="Preferred">
                                    <label class="mt-2 flex items-center gap-2 text-sm text-slate-600">
                                        <input type="checkbox" name="suppliers[{{ $supplier->id }}][is_preferred]" value="1"
                                               @checked($link?->pivot->is_preferred)
                                               class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                        Ring first
                                    </label>
                                </x-field>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>

    <div class="space-y-6">
        <x-card title="Money" subtitle="Amounts in cents.">
            <div class="grid grid-cols-2 gap-4">
                <x-field label="Cost">
                    <x-input type="number" min="0" name="cost_cents" value="{{ old('cost_cents', $part->cost_cents) }}" />
                </x-field>
                <x-field label="Price">
                    <x-input type="number" min="0" name="price_cents" value="{{ old('price_cents', $part->price_cents) }}" />
                </x-field>
                <x-field label="Core Charge" class="col-span-2"
                         hint="Refunded when the old unit comes back, so it is never marked up.">
                    <x-input type="number" min="0" name="core_charge_cents" value="{{ old('core_charge_cents', $part->core_charge_cents) }}" />
                </x-field>
            </div>
        </x-card>

        <x-card title="Stocking">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <x-field label="Reorder Point">
                        <x-input type="number" min="0" name="reorder_point" value="{{ old('reorder_point', $part->reorder_point) }}" />
                    </x-field>
                    <x-field label="Reorder Quantity">
                        <x-input type="number" min="0" name="reorder_qty" value="{{ old('reorder_qty', $part->reorder_qty) }}" />
                    </x-field>
                </div>
                @unless ($part->exists)
                    <x-field label="Opening Count" hint="Written to the ledger as an adjustment, so the stock has a stated origin.">
                        <x-input type="number" name="opening_qty" value="{{ old('opening_qty', 0) }}" />
                    </x-field>
                @endunless
                <x-toggle name="is_stocked" :checked="old('is_stocked', $part->is_stocked)"
                          label="Stocked" description="Turn off for a part always ordered in for the job." />
                <x-toggle name="is_taxable" :checked="old('is_taxable', $part->is_taxable)" label="Taxable" />
                <x-toggle name="is_active" :checked="old('is_active', $part->is_active)" label="Active" />
            </div>
        </x-card>
    </div>
</div>
