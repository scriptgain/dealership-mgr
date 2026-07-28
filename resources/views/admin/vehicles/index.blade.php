<x-layouts.app title="Vehicles">
    <x-page-header
        eyebrow="Shop"
        title="Vehicles"
        icon="wrench-screwdriver"
        subtitle="Every vehicle you service, with its history attached.">
        <x-slot:primary>
            <x-button href="{{ route('vehicles.create') }}" icon="plus">Add Vehicle</x-button>
        </x-slot:primary>
    </x-page-header>

    @if ($vehicles->isEmpty() && ! array_filter($filters))
        <x-card>
            <x-empty-state icon="wrench-screwdriver" title="No Vehicles Yet"
                description="A vehicle holds its own service history, so the next time it comes in you can see everything you have done to it rather than searching by the customer's name."
                :steps="[
                    'Add a vehicle and attach it to its owner.',
                    'Pick it on a repair order so the visit lands in its history.',
                    'Record the odometer on each visit to project the next service.',
                ]">
                <x-slot:action>
                    <x-button icon="plus" href="{{ route('vehicles.create') }}">Add Your First Vehicle</x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <div x-data="{
                selected: [],
                allIds: [{{ $vehicles->pluck('id')->implode(',') }}],
                submitBulk() {
                    const form = this.$refs.bulkForm;
                    form.querySelectorAll('input.js-dyn').forEach(node => node.remove());
                    this.selected.forEach(id => {
                        const input = document.createElement('input');
                        input.type = 'hidden'; input.name = 'ids[]'; input.value = id; input.className = 'js-dyn';
                        form.appendChild(input);
                    });
                    form.submit();
                }
            }">
            <form method="POST" action="{{ route('vehicles.bulk-destroy') }}" x-ref="bulkForm" class="hidden">@csrf @method('DELETE')</form>

            <x-data-surface>
                <x-slot:toolbar>
                    <x-segmented label="Vehicle Status">
                        @foreach ($tabs as $tab)
                            <a href="{{ $tab['href'] }}" class="vx-seg-item {{ $tab['active'] ? 'is-active' : '' }}"
                                @if ($tab['active']) aria-current="page" @endif>
                                {{ $tab['label'] }} <span class="vx-seg-count">{{ $tab['count'] }}</span>
                            </a>
                        @endforeach
                    </x-segmented>
                </x-slot:toolbar>

                <x-slot:search>
                    <form method="GET" action="{{ route('vehicles.index') }}" class="flex flex-wrap items-center gap-2">
                        @if (! empty($filters['state']))<input type="hidden" name="state" value="{{ $filters['state'] }}">@endif
                        <div class="relative">
                            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <label for="vehicle-search" class="sr-only">Search Vehicles</label>
                            <input id="vehicle-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                                placeholder="VIN, Plate, Make, Model, Or Owner"
                                class="block w-full min-w-0 rounded-lg border-0 bg-white py-1.5 pl-9 pr-3 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500 sm:w-72">
                        </div>
                        <x-button type="submit" variant="secondary" size="sm">Search</x-button>
                        @if (! empty($filters['q']))
                            <x-button variant="ghost" size="sm" href="{{ route('vehicles.index', array_filter(['state' => $filters['state'] ?? ''])) }}">Clear</x-button>
                        @endif
                    </form>
                </x-slot:search>

                <x-slot:bulk>
                    <div x-show="selected.length" x-cloak class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-200 bg-brand-50 px-4 py-2.5">
                        <span class="text-sm font-medium text-brand-900"><span x-text="selected.length"></span> Selected</span>
                        <div class="flex items-center gap-2">
                            <x-button type="button" variant="ghost" size="sm" x-on:click="selected = []">Clear Selection</x-button>
                            <x-button type="button" variant="danger" size="sm" icon="trash"
                                x-on:click="$dispatch('open-modal', 'bulk-delete-vehicles')">Delete Selected</x-button>
                        </div>
                    </div>
                </x-slot:bulk>

                @if ($vehicles->isEmpty())
                    <x-empty-state icon="search" title="No Vehicles Match These Filters"
                        description="Nothing here fits the current tab and search. Widen the search or switch back to All.">
                        <x-slot:action>
                            <x-button href="{{ route('vehicles.index') }}" variant="secondary" size="sm">Show All Vehicles</x-button>
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th class="vx-col-select"><span class="sr-only">Select</span>@include('admin._select-all-toggle')</th>
                                <th>Vehicle</th>
                                <th>Owner</th>
                                <th>Plate</th>
                                <th>VIN</th>
                                <th class="text-right">Mileage</th>
                                <th class="text-right">Visits</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vehicles as $vehicle)
                                <tr class="vx-rail vx-rail-{{ $vehicle->is_active ? 'success' : 'neutral' }}">
                                    <td class="vx-col-select">@include('admin._select-toggle', ['id' => $vehicle->id])</td>
                                    <td>
                                        <a href="{{ route('vehicles.show', $vehicle) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $vehicle->name }}</a>
                                        @if ($vehicle->color)
                                            <span class="block text-xs text-slate-500">{{ $vehicle->color }}</span>
                                        @endif
                                    </td>
                                    <td class="text-slate-600">{{ $vehicle->customer?->name ?? 'No Owner' }}</td>
                                    <td class="text-slate-600">{{ $vehicle->plate_label ?? '—' }}</td>
                                    <td class="font-mono text-xs text-slate-500" @if ($vehicle->vin) data-tip="{{ $vehicle->vin }}" @endif>{{ $vehicle->short_vin ?? '—' }}</td>
                                    <td class="tabular text-right text-slate-700">{{ $vehicle->mileage ? number_format($vehicle->mileage) : '—' }}</td>
                                    <td class="tabular text-right text-slate-700">{{ $vehicle->work_orders_count }}</td>
                                    <td><x-badge :color="$vehicle->is_active ? 'success' : 'neutral'" dot>{{ $vehicle->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-data-surface>

            <x-modal name="bulk-delete-vehicles" title="Delete Selected Vehicles?" icon="warning" tone="danger" maxWidth="max-w-md">
                This permanently removes the selected vehicles. Their repair orders and invoices are kept and simply unlinked. This cannot be undone.
                <x-slot:footer>
                    <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'bulk-delete-vehicles')">Cancel</x-button>
                    <x-button variant="danger" size="sm" icon="trash"
                        x-on:click="submitBulk(); $dispatch('close-modal', 'bulk-delete-vehicles')">Delete Vehicles</x-button>
                </x-slot:footer>
            </x-modal>
        </div>

        <div class="mt-6">{{ $vehicles->links() }}</div>
    @endif
</x-layouts.app>
