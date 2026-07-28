<x-layouts.app title="Suppliers">
    <x-page-header title="Suppliers" icon="truck" subtitle="Factors and dealers, and what they carry.">
        <x-slot:actions>
            <x-button href="{{ route('suppliers.create') }}" icon="plus">New Supplier</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card flush>
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-200 px-5 py-4">
            <x-field label="Search" class="min-w-56 flex-1">
                <x-input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, account number, or contact" />
            </x-field>
            <x-button type="submit" variant="secondary" icon="search">Filter</x-button>
        </form>

        @if ($suppliers->count())
            <x-table flush>
                <thead>
                    <tr><th>Supplier</th><th>Contact</th><th>Terms</th><th class="text-right">Lead Time</th><th class="text-right">Parts</th><th class="text-right">Open Orders</th></tr>
                </thead>
                <tbody>
                    @foreach ($suppliers as $supplier)
                        <tr>
                            <td>
                                <a href="{{ route('suppliers.show', $supplier) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $supplier->name }}</a>
                                <span class="block text-xs text-slate-500">{{ $supplier->account_number ?: 'no account number' }}</span>
                            </td>
                            <td class="text-slate-600">
                                {{ $supplier->contact_name ?: '-' }}
                                @if ($supplier->phone)<span class="block text-xs text-slate-400">{{ $supplier->phone }}</span>@endif
                            </td>
                            <td class="text-slate-600">{{ $supplier->terms ?: '-' }}</td>
                            <td class="text-right text-slate-600">{{ $supplier->lead_time_days !== null ? $supplier->lead_time_days.' days' : '-' }}</td>
                            <td class="text-right text-slate-600">{{ $supplier->parts_count }}</td>
                            <td class="text-right">
                                @if ($supplier->openOrderCount())
                                    <x-badge color="info">{{ $supplier->openOrderCount() }}</x-badge>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
            <div class="px-5 py-4">{{ $suppliers->links() }}</div>
        @else
            <x-empty-state title="No Suppliers Yet" icon="truck"
                description="Add the factors you buy from. Each part can carry a different number, cost, and lead time per supplier, so the shop always knows who to ring when a car is on the lift." />
        @endif
    </x-card>
</x-layouts.app>
