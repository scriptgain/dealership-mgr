<x-layouts.app title="Inspections">
    <x-page-header
        eyebrow="Shop"
        title="Inspections"
        icon="eye"
        subtitle="Photo inspections the customer approves line by line.">
        <x-slot:primary>
            <x-button href="{{ route('inspections.create') }}" icon="plus">New Inspection</x-button>
        </x-slot:primary>
    </x-page-header>

    @if ($inspections->isEmpty() && ! array_filter($filters))
        <x-card>
            <x-empty-state icon="eye" title="No Inspections Yet"
                description="An inspection is how you show a customer the worn pad instead of telling them about it. They see your photos on their phone, approve the work they want, and only that work gets billed."
                :steps="[
                    'Start an inspection against a vehicle and load a checklist group.',
                    'Add photos to anything you are recommending.',
                    'Send the link. The customer approves or declines each line.',
                    'Push what they approved onto the repair order.',
                ]">
                <x-slot:action>
                    <x-button icon="plus" href="{{ route('inspections.create') }}">Start Your First Inspection</x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <x-data-surface>
            <x-slot:toolbar>
                <x-segmented label="Inspection Status">
                    @foreach ($tabs as $tab)
                        <a href="{{ $tab['href'] }}" class="vx-seg-item {{ $tab['active'] ? 'is-active' : '' }}"
                            @if ($tab['active']) aria-current="page" @endif>
                            {{ $tab['label'] }} <span class="vx-seg-count">{{ $tab['count'] }}</span>
                        </a>
                    @endforeach
                </x-segmented>
            </x-slot:toolbar>

            <x-slot:search>
                <form method="GET" action="{{ route('inspections.index') }}" class="flex flex-wrap items-center gap-2">
                    @if (! empty($filters['status']))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
                    <div class="relative">
                        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <label for="ins-search" class="sr-only">Search Inspections</label>
                        <input id="ins-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                            placeholder="Number, VIN, Plate, Or Vehicle"
                            class="block w-full min-w-0 rounded-lg border-0 bg-white py-1.5 pl-9 pr-3 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500 sm:w-72">
                    </div>
                    <x-button type="submit" variant="secondary" size="sm">Search</x-button>
                    @if (! empty($filters['q']))
                        <x-button variant="ghost" size="sm" href="{{ route('inspections.index', array_filter(['status' => $filters['status'] ?? ''])) }}">Clear</x-button>
                    @endif
                </form>
            </x-slot:search>

            @if ($inspections->isEmpty())
                <x-empty-state icon="search" title="No Inspections Match These Filters"
                    description="Nothing here fits the current tab and search. Widen the search or switch back to All.">
                    <x-slot:action>
                        <x-button href="{{ route('inspections.index') }}" variant="secondary" size="sm">Show All</x-button>
                    </x-slot:action>
                </x-empty-state>
            @else
                <x-table flush>
                    <thead>
                        <tr>
                            <th>Inspection</th>
                            <th>Vehicle</th>
                            <th>Customer</th>
                            <th class="text-right">Findings</th>
                            <th class="text-right">Approved</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($inspections as $inspection)
                            @php $counts = $inspection->decisionCounts(); @endphp
                            <tr class="vx-rail vx-rail-{{ $inspection->statusBadge() }}">
                                <td>
                                    <a href="{{ route('inspections.show', $inspection) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $inspection->number }}</a>
                                    <span class="block text-xs text-slate-500">{{ $inspection->created_at->format(config('dealership.date_format', 'M j, Y')) }}</span>
                                </td>
                                <td class="text-slate-600">
                                    {{ $inspection->vehicle?->name ?? 'No Vehicle' }}
                                    @if ($inspection->vehicle?->plate_label)
                                        <span class="block text-xs text-slate-500">{{ $inspection->vehicle->plate_label }}</span>
                                    @endif
                                </td>
                                <td class="text-slate-600">{{ $inspection->customer?->name ?? 'No Customer' }}</td>
                                <td class="tabular text-right text-slate-700">{{ $inspection->items_count }}</td>
                                <td class="tabular text-right text-slate-700">
                                    {{ $counts['approved'] }}
                                    @if ($counts['declined'])
                                        <span class="text-xs text-slate-400">/ {{ $counts['declined'] }} declined</span>
                                    @endif
                                </td>
                                <td><x-badge :color="$inspection->statusBadge()" dot>{{ $inspection->statusLabel() }}</x-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            @endif
        </x-data-surface>

        <div class="mt-6">{{ $inspections->links() }}</div>
    @endif
</x-layouts.app>
