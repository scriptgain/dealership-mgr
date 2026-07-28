<x-layouts.app :title="$order->number">
    <x-page-header :title="$order->number" icon="document" :subtitle="$order->supplier?->name"
        :back="['href' => route('purchase-orders.index'), 'label' => 'Purchase Orders']">
        <x-slot:meta>
            <x-badge :color="$order->statusBadge()" dot>{{ \Illuminate\Support\Str::headline($order->status) }}</x-badge>
            @if ($order->expected_on)<span class="text-xs text-slate-500">Expected {{ $order->expected_on->format('j M Y') }}</span>@endif
            @if ($order->workOrder)
                <a href="{{ route('work-orders.show', $order->workOrder) }}" class="text-xs font-semibold text-brand-700 hover:underline">For {{ $order->workOrder->number }}</a>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            @if ($order->status === 'draft')
                <form method="POST" action="{{ route('purchase-orders.place', $order) }}">
                    @csrf
                    <x-button type="submit" size="sm" icon="truck">Place Order</x-button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('purchase-orders.receive', $order) }}">
        @csrf
        <x-card title="Lines" flush>
            <x-table flush>
                <thead>
                    <tr>
                        <th>Part</th>
                        <th class="text-right">Ordered</th>
                        <th class="text-right">Received</th>
                        <th class="text-right">Unit Cost</th>
                        <th class="text-right">Line Total</th>
                        @if (in_array($order->status, ['ordered', 'partial'], true))
                            <th class="text-right">Book In</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>
                                <a href="{{ route('parts.show', $item->part) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $item->part?->part_number }}</a>
                                <span class="block text-xs text-slate-500">{{ $item->part?->name }}</span>
                            </td>
                            <td class="text-right text-slate-600">{{ $item->qty }}</td>
                            <td class="text-right {{ $item->outstanding() > 0 ? 'font-semibold text-amber-700' : 'text-slate-600' }}">
                                {{ $item->qty_received }}
                            </td>
                            <td class="text-right text-slate-600">{{ \App\Support\Money::format($item->unit_cost_cents) }}</td>
                            <td class="text-right text-slate-700">{{ \App\Support\Money::format($item->lineTotalCents()) }}</td>
                            @if (in_array($order->status, ['ordered', 'partial'], true))
                                <td class="w-28 text-right">
                                    <x-input type="number" min="0" :max="$item->outstanding()" class="text-right"
                                             name="received[{{ $item->id }}]" value="{{ $item->outstanding() }}" />
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </x-card>

        @if (in_array($order->status, ['ordered', 'partial'], true))
            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-500">
                    Receiving is capped at what is outstanding, so booking the same delivery in twice cannot put the stock on twice.
                </p>
                <x-button type="submit" icon="check">Book In Delivery</x-button>
            </div>
        @endif
    </form>

    <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-3">
        <x-stat label="Order Total" :value="\App\Support\Money::format($order->totalCents())" icon="tag" />
        <x-stat label="Outstanding Units" :value="$order->outstandingUnits()" icon="truck"
            :trendColor="$order->outstandingUnits() > 0 ? 'danger' : 'success'" />
        <x-stat label="Placed" :value="$order->ordered_at?->format('j M Y') ?? 'Not Yet'" icon="clock" />
    </div>

    @if ($order->notes)
        <x-card class="mt-6" title="Notes">
            <p class="whitespace-pre-line text-sm text-slate-700">{{ $order->notes }}</p>
        </x-card>
    @endif
</x-layouts.app>
