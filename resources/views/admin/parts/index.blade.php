<x-layouts.app title="Parts">
    <x-page-header title="Parts" icon="box"
        subtitle="The shelf, and what it is worth. On hand is the sum of the ledger, never a typed-in number.">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('purchase-orders.create') }}" icon="truck">Order Parts</x-button>
            <x-button href="{{ route('parts.create') }}" icon="plus">New Part</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($lowCount > 0)
        <x-alert type="warning" title="Below Reorder Point" class="mb-6">
            {{ $lowCount }} {{ \Illuminate\Support\Str::plural('part', $lowCount) }} at or under the reorder point.
            <a href="{{ route('purchase-orders.create') }}" class="font-semibold underline">Raise a purchase order</a>.
        </x-alert>
    @endif

    <x-card flush>
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-200 px-5 py-4">
            <x-field label="Search" class="min-w-56 flex-1">
                <x-input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Part number, name, brand, or bin" />
            </x-field>
            <x-field label="Category">
                <x-select name="category">
                    <option value="">All</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c }}" @selected(($filters['category'] ?? '') === $c)>{{ $c }}</option>
                    @endforeach
                </x-select>
            </x-field>
            <x-button type="submit" variant="secondary" icon="search">Filter</x-button>
        </form>

        @if ($parts->count())
            <x-table flush>
                <thead>
                    <tr>
                        <th>Part</th><th>Bin</th>
                        <th class="text-right">On Hand</th><th class="text-right">On Order</th>
                        <th class="text-right">Cost</th><th class="text-right">Price</th><th class="text-right">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($parts as $part)
                        @php($onHand = $part->onHand())
                        <tr>
                            <td>
                                <a href="{{ route('parts.show', $part) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $part->part_number }}</a>
                                <span class="block text-xs text-slate-500">{{ $part->name }}{{ $part->brand ? ' - '.$part->brand : '' }}</span>
                            </td>
                            <td class="font-mono text-xs text-slate-500">{{ $part->bin_location ?: '-' }}</td>
                            <td class="text-right">
                                <span class="{{ $part->isBelowReorderPoint() ? 'font-semibold text-rose-700' : 'text-slate-700' }}">{{ $onHand }}</span>
                                @if ($part->reorder_point !== null)
                                    <span class="block text-xs text-slate-400">min {{ $part->reorder_point }}</span>
                                @endif
                            </td>
                            <td class="text-right text-slate-500">{{ $part->onOrder() ?: '-' }}</td>
                            <td class="text-right text-slate-500">{{ \App\Support\Money::format($part->bestCostCents()) }}</td>
                            <td class="text-right text-slate-700">{{ \App\Support\Money::format($part->price_cents) }}</td>
                            <td class="text-right text-slate-600">{{ $part->marginPercent() !== null ? $part->marginPercent().'%' : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
            <div class="px-5 py-4">{{ $parts->links() }}</div>
        @else
            <x-empty-state title="No Parts On File" icon="box"
                description="Add the parts you stock. Every movement is recorded, so the count on screen and the reason for it are the same record: you can always answer where the other two went." />
        @endif
    </x-card>
</x-layouts.app>
