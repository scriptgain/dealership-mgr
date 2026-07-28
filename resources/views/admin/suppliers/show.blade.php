<x-layouts.app :title="$supplier->name">
    <x-page-header :title="$supplier->name" icon="truck" :subtitle="$supplier->account_number"
        :back="['href' => route('suppliers.index'), 'label' => 'Suppliers']">
        <x-slot:actions>
            <x-button variant="secondary" size="sm" href="{{ route('suppliers.edit', $supplier) }}" icon="edit">Edit</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-card title="Purchase Orders" flush>
                @if ($orders->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-slate-500">Nothing ordered from this supplier yet.</p>
                @else
                    <x-table flush>
                        <thead><tr><th>Order</th><th>Expected</th><th class="text-right">Lines</th><th class="text-right">Total</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($orders as $order)
                                <tr>
                                    <td>
                                        <a href="{{ route('purchase-orders.show', $order) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $order->number }}</a>
                                        <span class="block text-xs text-slate-500">{{ $order->created_at->format('j M Y') }}</span>
                                    </td>
                                    <td class="text-slate-600">{{ $order->expected_on?->format('j M Y') ?? '-' }}</td>
                                    <td class="text-right text-slate-600">{{ $order->items->count() }}</td>
                                    <td class="text-right text-slate-700">{{ \App\Support\Money::format($order->totalCents()) }}</td>
                                    <td><x-badge :color="$order->statusBadge()" dot>{{ \Illuminate\Support\Str::headline($order->status) }}</x-badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>

            <x-card title="Parts Carried" flush>
                @if ($supplier->parts->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-slate-500">No parts linked to this supplier.</p>
                @else
                    <x-table flush>
                        <thead><tr><th>Part</th><th>Their Number</th><th class="text-right">Cost</th><th class="text-right">Lead</th></tr></thead>
                        <tbody>
                            @foreach ($supplier->parts as $part)
                                <tr>
                                    <td>
                                        <a href="{{ route('parts.show', $part) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $part->part_number }}</a>
                                        <span class="block text-xs text-slate-500">{{ $part->name }}</span>
                                    </td>
                                    <td class="font-mono text-xs text-slate-500">{{ $part->pivot->supplier_part_number ?: '-' }}</td>
                                    <td class="text-right text-slate-700">
                                        {{ $part->pivot->cost_cents !== null ? \App\Support\Money::format($part->pivot->cost_cents) : '-' }}
                                    </td>
                                    <td class="text-right text-slate-600">
                                        {{ $part->pivot->lead_time_days !== null ? $part->pivot->lead_time_days.'d' : '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Contact">
                <dl class="space-y-3 text-sm">
                    @foreach ([
                        'Contact' => $supplier->contact_name,
                        'Phone' => $supplier->phone,
                        'Email' => $supplier->email,
                        'Terms' => $supplier->terms,
                        'Lead Time' => $supplier->lead_time_days !== null ? $supplier->lead_time_days.' days' : null,
                    ] as $label => $value)
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="text-right font-medium text-slate-900">{{ $value ?: '-' }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($supplier->website)
                    <a href="{{ $supplier->website }}" target="_blank" rel="noopener"
                       class="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-transparent px-2 py-1 text-sm font-semibold text-brand-700 transition hover:border-brand-300 hover:bg-brand-50">
                        <x-icon name="external" class="w-4 h-4" /> Trade Site
                    </a>
                @endif
            </x-card>

            @if ($supplier->address)
                <x-card title="Address">
                    <p class="whitespace-pre-line text-sm text-slate-700">{{ $supplier->address }}</p>
                </x-card>
            @endif

            @if ($supplier->notes)
                <x-card title="Notes">
                    <p class="whitespace-pre-line text-sm text-slate-700">{{ $supplier->notes }}</p>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.app>
