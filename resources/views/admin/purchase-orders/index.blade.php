<x-layouts.app title="Purchase Orders">
    <x-page-header title="Purchase Orders" icon="document" subtitle="What is on order, and what has landed.">
        <x-slot:actions>
            <x-button href="{{ route('purchase-orders.create') }}" icon="plus">New Order</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card flush>
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-200 px-5 py-4">
            <x-field label="Supplier">
                <x-select name="supplier">
                    <option value="">All</option>
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->id }}" @selected((string) ($filters['supplier'] ?? '') === (string) $s->id)>{{ $s->name }}</option>
                    @endforeach
                </x-select>
            </x-field>
            <x-field label="Status">
                <x-select name="status">
                    <option value="">All</option>
                    @foreach (['draft', 'ordered', 'partial', 'received', 'cancelled'] as $st)
                        <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ \Illuminate\Support\Str::headline($st) }}</option>
                    @endforeach
                </x-select>
            </x-field>
            <x-button type="submit" variant="secondary" icon="filter">Filter</x-button>
        </form>

        @if ($orders->count())
            <x-table flush>
                <thead>
                    <tr><th>Order</th><th>Supplier</th><th>Expected</th><th class="text-right">Outstanding</th><th class="text-right">Total</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td>
                                <a href="{{ route('purchase-orders.show', $order) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $order->number }}</a>
                                <span class="block text-xs text-slate-500">{{ $order->created_at->format('j M Y') }}</span>
                            </td>
                            <td class="text-slate-600">{{ $order->supplier?->name }}</td>
                            <td class="text-slate-600">{{ $order->expected_on?->format('j M Y') ?? '-' }}</td>
                            <td class="text-right">
                                @if ($order->outstandingUnits() > 0)
                                    <span class="font-semibold text-amber-700">{{ $order->outstandingUnits() }}</span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="text-right text-slate-700">{{ \App\Support\Money::format($order->totalCents()) }}</td>
                            <td><x-badge :color="$order->statusBadge()" dot>{{ \Illuminate\Support\Str::headline($order->status) }}</x-badge></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
            <div class="px-5 py-4">{{ $orders->links() }}</div>
        @else
            <x-empty-state title="Nothing On Order" icon="document"
                description="Raise an order and each delivery is booked in against it. Receiving is cumulative and capped at what was ordered, so the same delivery cannot be counted twice." />
        @endif
    </x-card>
</x-layouts.app>
