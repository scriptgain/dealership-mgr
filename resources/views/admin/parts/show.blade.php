<x-layouts.app :title="$part->part_number">
    <x-page-header :title="$part->part_number" icon="box" :subtitle="$part->name"
        :back="['href' => route('parts.index'), 'label' => 'Parts']">
        <x-slot:meta>
            @if ($part->brand)<x-badge color="neutral">{{ $part->brand }}</x-badge>@endif
            @if ($part->category)<x-badge color="neutral">{{ $part->category }}</x-badge>@endif
            @if ($part->bin_location)<x-badge color="info">Bin {{ $part->bin_location }}</x-badge>@endif
            @if ($part->supersededBy)
                <x-badge color="warning">Superseded by {{ $part->supersededBy->part_number }}</x-badge>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            <x-button variant="secondary" size="sm" href="{{ route('purchase-orders.create', ['part' => $part->id]) }}" icon="truck">Order</x-button>
            <x-button variant="secondary" size="sm" href="{{ route('parts.edit', $part) }}" icon="edit">Edit</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="On Hand" :value="$part->onHand()" icon="box"
            :trend="$part->reorder_point !== null ? 'reorder at '.$part->reorder_point : null"
            :trendColor="$part->isBelowReorderPoint() ? 'danger' : 'success'" />
        <x-stat label="On Order" :value="$part->onOrder()" icon="truck" />
        <x-stat label="Best Cost" :value="\App\Support\Money::format($part->bestCostCents())" icon="tag" />
        <x-stat label="Margin" :value="$part->marginPercent() !== null ? $part->marginPercent().'%' : 'n/a'" icon="percent"
            :trend="\App\Support\Money::format($part->price_cents).' selling'" />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 lg:col-span-2">
            <x-card title="Stock Ledger" subtitle="Every movement, newest first. This is where the on-hand figure comes from." flush>
                @if ($movements->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-slate-500">No movements recorded.</p>
                @else
                    <x-table flush>
                        <thead><tr><th>When</th><th>Movement</th><th class="text-right">Qty</th><th>Reason</th><th>By</th></tr></thead>
                        <tbody>
                            @foreach ($movements as $movement)
                                <tr>
                                    <td class="whitespace-nowrap text-slate-500">{{ $movement->created_at->format('j M Y, H:i') }}</td>
                                    <td><x-badge :color="$movement->badge()" dot>{{ $movement->label() }}</x-badge></td>
                                    <td class="text-right font-semibold {{ $movement->qty < 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                                        {{ $movement->qty > 0 ? '+' : '' }}{{ $movement->qty }}
                                    </td>
                                    <td class="text-slate-600">
                                        @if ($movement->workOrder)
                                            <a href="{{ route('work-orders.show', $movement->workOrder) }}" class="font-medium text-slate-700 hover:text-brand-700">{{ $movement->workOrder->number }}</a>
                                            <span class="block text-xs text-slate-400">{{ $movement->reason }}</span>
                                        @else
                                            {{ $movement->reason ?: '-' }}
                                        @endif
                                    </td>
                                    <td class="text-slate-500">{{ $movement->user?->name ?? 'System' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Adjust Stock" subtitle="A correction is a ledger row, not an edit to a count.">
                <form method="POST" action="{{ route('parts.adjust', $part) }}" class="space-y-4">
                    @csrf
                    <x-field label="Movement">
                        <x-select name="kind">
                            <option value="adjusted">Adjustment (count correction)</option>
                            <option value="returned">Return To Stock</option>
                            <option value="scrapped">Scrapped</option>
                        </x-select>
                    </x-field>
                    <x-field label="Quantity" hint="Negative takes stock off for an adjustment.">
                        <x-input type="number" name="qty" value="1" required />
                    </x-field>
                    <x-field label="Reason" required>
                        <x-input name="reason" placeholder="Annual count, damaged in rack, wrong part ordered" required />
                    </x-field>
                    <x-button type="submit" class="w-full" icon="check">Record Movement</x-button>
                </form>
            </x-card>

            <x-card title="Suppliers" flush>
                @if ($part->suppliers->isEmpty())
                    <p class="px-5 py-6 text-center text-sm text-slate-500">No supplier linked.</p>
                @else
                    <ul class="divide-y divide-slate-200">
                        @foreach ($part->suppliers->sortByDesc('pivot.is_preferred') as $supplier)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <a href="{{ route('suppliers.show', $supplier) }}" class="text-sm font-medium text-slate-800 hover:text-brand-700">{{ $supplier->name }}</a>
                                    <span class="block text-xs text-slate-500">
                                        {{ $supplier->pivot->supplier_part_number ?: 'no part number' }}
                                        @if ($supplier->pivot->lead_time_days !== null)
                                            - {{ $supplier->pivot->lead_time_days }} day lead
                                        @endif
                                    </span>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="text-sm font-semibold text-slate-700">
                                        {{ $supplier->pivot->cost_cents !== null ? \App\Support\Money::format($supplier->pivot->cost_cents) : '-' }}
                                    </span>
                                    @if ($supplier->pivot->is_preferred)
                                        <span class="block text-xs font-semibold text-brand-700">Preferred</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            @if ($part->supersedes->count())
                <x-card title="Replaces" flush>
                    <ul class="divide-y divide-slate-200">
                        @foreach ($part->supersedes as $old)
                            <li class="px-5 py-2.5">
                                <a href="{{ route('parts.show', $old) }}" class="text-sm font-medium text-slate-800 hover:text-brand-700">{{ $old->part_number }}</a>
                                <span class="block text-xs text-slate-500">{{ $old->name }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.app>
