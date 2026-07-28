<x-layouts.app title="Canned Jobs">
    <x-page-header eyebrow="Shop" title="Canned Jobs" icon="bolt"
        subtitle="Standard jobs priced once, so a brake quote is one click and not four typed lines.">
        <x-slot:primary>
            <x-button href="{{ route('canned-jobs.create') }}" icon="plus">Add Canned Job</x-button>
        </x-slot:primary>
    </x-page-header>

    @if ($jobs->isEmpty() && ! array_filter($filters))
        <x-card>
            <x-empty-state icon="bolt" title="No Canned Jobs Yet"
                description="A canned job is the work you do over and over: pads and rotors, an oil change, an alignment. Price it once with its book time and parts, and every quote after that is one click at a consistent price."
                :steps="[
                    'Add a job with its labour hours and parts cost.',
                    'Set a rate override only where you bill differently, like fleet work.',
                    'Pick it when building a repair order or an inspection recommendation.',
                ]">
                <x-slot:action>
                    <x-button icon="plus" href="{{ route('canned-jobs.create') }}">Add Your First Job</x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <x-data-surface>
            <x-slot:search>
                <form method="GET" action="{{ route('canned-jobs.index') }}" class="flex flex-wrap items-center gap-2">
                    <div class="relative">
                        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <label for="cj-search" class="sr-only">Search Canned Jobs</label>
                        <input id="cj-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name Or Category"
                            class="block w-full min-w-0 rounded-lg border-0 bg-white py-1.5 pl-9 pr-3 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500 sm:w-64">
                    </div>
                    <x-button type="submit" variant="secondary" size="sm">Search</x-button>
                    @if (! empty($filters['q']))
                        <x-button variant="ghost" size="sm" href="{{ route('canned-jobs.index') }}">Clear</x-button>
                    @endif
                </form>
            </x-slot:search>

            @if ($jobs->isEmpty())
                <x-empty-state icon="search" title="Nothing Matches That Search"
                    description="No canned job matches. Try a shorter search.">
                    <x-slot:action>
                        <x-button href="{{ route('canned-jobs.index') }}" variant="secondary" size="sm">Show All</x-button>
                    </x-slot:action>
                </x-empty-state>
            @else
                <x-table flush>
                    <thead>
                        <tr>
                            <th>Job</th>
                            <th>Category</th>
                            <th class="text-right">Labour</th>
                            <th class="text-right">Parts</th>
                            <th class="text-right">Bills At</th>
                            <th>Status</th>
                            <th class="text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($jobs as $job)
                            <tr class="vx-rail vx-rail-{{ $job->is_active ? 'success' : 'neutral' }}">
                                <td>
                                    <a href="{{ route('canned-jobs.edit', $job) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $job->name }}</a>
                                    @if ($job->description)
                                        <span class="block max-w-md truncate text-xs text-slate-500" data-tip="{{ $job->description }}">{{ $job->description }}</span>
                                    @endif
                                </td>
                                <td class="text-slate-600">{{ $job->category ?: '-' }}</td>
                                <td class="tabular text-right text-slate-700">
                                    {{ $job->labourHours() }} h
                                    <span class="block text-xs text-slate-500">at <x-money :cents="$job->rateCents()" /></span>
                                </td>
                                <td class="tabular text-right text-slate-700"><x-money :cents="$job->parts_cents" /></td>
                                <td class="tabular text-right font-semibold text-slate-900"><x-money :cents="$job->totalCents()" /></td>
                                <td><x-badge :color="$job->is_active ? 'success' : 'neutral'" dot>{{ $job->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                                <td class="text-right">
                                    <x-delete-button :action="route('canned-jobs.destroy', $job)" :name="'del-cj-'.$job->id"
                                        label="Delete" size="sm" title="Delete This Canned Job?"
                                        message="This removes the canned job. Repair orders that already used it keep their lines." />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            @endif
        </x-data-surface>

        <p class="mt-4 text-xs text-slate-500">
            Shop labour rate is <x-money :cents="$rate" /> per hour. Jobs without an override bill at that rate.
        </p>

        <div class="mt-6">{{ $jobs->links() }}</div>
    @endif
</x-layouts.app>
