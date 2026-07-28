<x-layouts.app :title="$vehicle->name">
    <x-page-header
        eyebrow="Vehicle"
        :title="$vehicle->name"
        icon="wrench-screwdriver"
        :subtitle="$vehicle->customer?->name"
        :back="['href' => route('vehicles.index'), 'label' => 'All Vehicles']">
        <x-slot:meta>
            <x-badge :color="$vehicle->is_active ? 'success' : 'neutral'" dot>{{ $vehicle->is_active ? 'Active' : 'Inactive' }}</x-badge>
            @if ($vehicle->plate_label)<x-badge color="neutral">{{ $vehicle->plate_label }}</x-badge>@endif
            @if ($vehicle->drivetrain)<x-badge color="neutral">{{ $vehicle->drivetrain }}</x-badge>@endif
        </x-slot:meta>

        <x-slot:actions>
            <x-button variant="secondary" size="sm" icon="plus" href="{{ route('work-orders.create', ['vehicle' => $vehicle->id]) }}">New Repair Order</x-button>
            <x-button variant="secondary" size="sm" icon="edit" href="{{ route('vehicles.edit', $vehicle) }}">Edit</x-button>
            <x-delete-button :action="route('vehicles.destroy', $vehicle)" name="delete-vehicle"
                label="Delete Vehicle" title="Delete This Vehicle?"
                message="This permanently removes the vehicle. Its repair orders and invoices are kept and unlinked. This cannot be undone." />
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Odometer" :value="$vehicle->currentMileage() ? number_format($vehicle->currentMileage()).' mi' : 'Not Recorded'"
            :hint="$vehicle->mileage_read_on ? 'Read '.$vehicle->mileage_read_on->format(config('dealership.date_format', 'M j, Y')) : null" icon="dashboard" />
        <x-stat label="Visits" :value="$history->total()" icon="truck" />
        <x-stat label="Last Serviced"
            :value="$vehicle->lastServicedAt()?->format(config('dealership.date_format', 'M j, Y')) ?? 'Never'" icon="clock" />
        <x-stat label="Average Use" :value="$milesPerDay ? number_format($milesPerDay * 30).' mi/mo' : 'Not Enough Data'"
            hint="Needs two completed visits at least a month apart." icon="chart" />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-card title="Service History" subtitle="Every visit this vehicle has made, newest first." flush>
                @if ($history->isEmpty())
                    <x-empty-state icon="truck" title="No Visits Yet"
                        description="Once this vehicle goes on a repair order, the visit and everything done to it shows up here.">
                        <x-slot:action>
                            <x-button size="sm" icon="plus" href="{{ route('work-orders.create', ['vehicle' => $vehicle->id]) }}">New Repair Order</x-button>
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th>Repair Order</th>
                                <th>Work Done</th>
                                <th class="text-right">Odometer</th>
                                <th class="text-right">Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($history as $order)
                                <tr class="vx-rail vx-rail-{{ $order->status_badge }}">
                                    <td>
                                        <a href="{{ route('work-orders.show', $order) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $order->number }}</a>
                                        <span class="block text-xs text-slate-500">
                                            {{ ($order->completed_at ?? $order->scheduled_at ?? $order->created_at)->format(config('dealership.date_format', 'M j, Y')) }}
                                        </span>
                                    </td>
                                    <td class="text-slate-600" data-tip="{{ $order->items->pluck('name')->implode(', ') ?: $order->title }}">
                                        {{ $order->title }}
                                        @if ($order->items->isNotEmpty())
                                            <span class="block text-xs text-slate-500">{{ $order->items->count() }} {{ \Illuminate\Support\Str::plural('line', $order->items->count()) }}</span>
                                        @endif
                                    </td>
                                    <td class="tabular text-right text-slate-700">{{ $order->mileage_out ? number_format($order->mileage_out) : '—' }}</td>
                                    <td class="tabular text-right text-slate-700"><x-money :cents="$order->subtotal_cents" /></td>
                                    <td><x-badge :color="$order->status_badge" dot>{{ $order->status_label }}</x-badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>

            @if ($history->hasPages())
                <div>{{ $history->links() }}</div>
            @endif
        </div>

        <div class="space-y-6">
            <x-card title="Details">
                <dl class="space-y-3 text-sm">
                    @foreach ([
                        'VIN' => $vehicle->vin,
                        'Plate' => $vehicle->plate_label,
                        'Engine' => $vehicle->engine,
                        'Transmission' => $vehicle->transmission,
                        'Drivetrain' => $vehicle->drivetrain,
                        'Color' => $vehicle->color,
                    ] as $label => $value)
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="min-w-0 truncate text-right font-medium text-slate-900 {{ $label === 'VIN' ? 'font-mono text-xs' : '' }}"
                                @if ($value) data-tip="{{ $value }}" @endif>{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>

            @if ($vehicle->customer)
                <x-card title="Owner">
                    <a href="{{ route('customers.show', $vehicle->customer) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $vehicle->customer->name }}</a>
                    <p class="mt-1 text-sm text-slate-500">{{ $vehicle->customer->email }}</p>
                </x-card>
            @endif

            @if ($openRequests->isNotEmpty())
                <x-card title="Open Requests" subtitle="Raised against this vehicle and not yet converted.">
                    <ul class="space-y-2 text-sm">
                        @foreach ($openRequests as $request)
                            <li>
                                <a href="{{ route('service-requests.show', $request) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $request->subject }}</a>
                                <span class="block text-xs text-slate-500">{{ $request->number }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @if ($quotes->isNotEmpty())
                <x-card title="Recent Estimates">
                    <ul class="space-y-2 text-sm">
                        @foreach ($quotes as $quote)
                            <li class="flex items-center justify-between gap-3">
                                <a href="{{ route('quotes.show', $quote) }}" class="min-w-0 truncate font-medium text-slate-900 hover:text-brand-700">{{ $quote->title }}</a>
                                <x-badge :color="$quote->status_badge ?? 'neutral'">{{ \Illuminate\Support\Str::headline($quote->status) }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @if ($vehicle->notes)
                <x-card title="Notes">
                    <p class="whitespace-pre-line text-sm text-slate-600">{{ $vehicle->notes }}</p>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.app>
