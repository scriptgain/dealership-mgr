<x-layouts.shop title="Inventory">

    {{-- Hero + search --}}
    <section class="bg-brand-900 text-white">
        <div class="mx-auto {{ $maxWidth ?? 'max-w-7xl' }} px-5 py-12 lg:py-16">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-300">Browse The Lot</p>
            <h1 class="mt-2 text-3xl lg:text-4xl font-black tracking-tight">Our Inventory</h1>
            <p class="mt-3 max-w-2xl text-brand-100">
                {{ $totalCount }} {{ Str::plural('Vehicle', $totalCount) }} Available Right Now. Every One Inspected And Reconditioned In Our Own Service Department.
            </p>

            <form method="GET" action="{{ route('shop.inventory') }}" class="mt-7 flex flex-wrap gap-2">
                <div class="relative flex-1 min-w-[240px]">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-brand-400">
                        <x-icon name="search" class="w-4 h-4" />
                    </span>
                    <input type="search" name="q" value="{{ $filters['q'] }}"
                           placeholder="Search Make, Model Or Stock Number"
                           class="w-full rounded-xl border border-brand-700 bg-brand-800 py-3 pl-11 pr-4 text-white placeholder:text-brand-400 focus:border-brand-400 focus:outline-none">
                </div>
                @foreach (['make', 'body_type', 'condition', 'max_price', 'sort'] as $carry)
                    @if ($filters[$carry] !== '')
                        <input type="hidden" name="{{ $carry }}" value="{{ $filters[$carry] }}">
                    @endif
                @endforeach
                <button type="submit" class="rounded-xl bg-white px-6 py-3 font-bold text-brand-900 transition hover:bg-brand-100">Search</button>
            </form>
        </div>
    </section>

    <div class="mx-auto {{ $maxWidth ?? 'max-w-7xl' }} px-5 py-10">
        <div class="grid gap-8 lg:grid-cols-[260px_1fr]">

            {{-- Filters --}}
            <aside>
                <form method="GET" action="{{ route('shop.inventory') }}" class="space-y-6 rounded-2xl border border-shop-line bg-white p-5">
                    @if ($filters['q'] !== '')
                        <input type="hidden" name="q" value="{{ $filters['q'] }}">
                    @endif

                    <div>
                        <h2 class="text-sm font-bold text-shop-ink">Filter</h2>
                        <p class="mt-1 text-xs text-shop-muted">Narrow The List, Then Share The Link.</p>
                    </div>

                    <div>
                        <label for="filter-make" class="block text-xs font-bold uppercase tracking-wider text-shop-muted">Make</label>
                        <select id="filter-make" name="make" class="mt-1.5 w-full rounded-lg border border-shop-line bg-white px-3 py-2 text-sm">
                            <option value="">All Makes</option>
                            @foreach ($makes as $make)
                                <option value="{{ $make }}" @selected($filters['make'] === $make)>{{ $make }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="filter-body" class="block text-xs font-bold uppercase tracking-wider text-shop-muted">Body Type</label>
                        <select id="filter-body" name="body_type" class="mt-1.5 w-full rounded-lg border border-shop-line bg-white px-3 py-2 text-sm">
                            <option value="">Any Body Type</option>
                            @foreach ($bodyTypes as $type)
                                <option value="{{ $type }}" @selected($filters['body_type'] === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="filter-condition" class="block text-xs font-bold uppercase tracking-wider text-shop-muted">Condition</label>
                        <select id="filter-condition" name="condition" class="mt-1.5 w-full rounded-lg border border-shop-line bg-white px-3 py-2 text-sm">
                            <option value="">New And Used</option>
                            @foreach ($conditions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['condition'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="filter-price" class="block text-xs font-bold uppercase tracking-wider text-shop-muted">Max Price</label>
                        <select id="filter-price" name="max_price" class="mt-1.5 w-full rounded-lg border border-shop-line bg-white px-3 py-2 text-sm">
                            <option value="">No Limit</option>
                            @foreach ([15000, 20000, 25000, 30000, 35000, 40000] as $cap)
                                <option value="{{ $cap }}" @selected($filters['max_price'] === (string) $cap)>Under ${{ number_format($cap) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-1">
                        <button type="submit" class="flex-1 rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-800">Apply</button>
                        <a href="{{ route('shop.inventory') }}" class="rounded-lg border border-shop-line px-4 py-2.5 text-sm font-semibold text-shop-muted transition hover:border-brand-500 hover:text-brand-700">Reset</a>
                    </div>
                </form>
            </aside>

            {{-- Results --}}
            <div>
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-shop-muted">
                        Showing <span class="font-bold text-shop-ink">{{ $vehicles->count() }}</span>
                        Of <span class="font-bold text-shop-ink">{{ $vehicles->total() }}</span> Vehicles
                    </p>
                    <form method="GET" action="{{ route('shop.inventory') }}" class="flex items-center gap-2">
                        @foreach (['q', 'make', 'body_type', 'condition', 'max_price'] as $carry)
                            @if ($filters[$carry] !== '')
                                <input type="hidden" name="{{ $carry }}" value="{{ $filters[$carry] }}">
                            @endif
                        @endforeach
                        <label for="sort" class="text-xs font-bold uppercase tracking-wider text-shop-muted">Sort</label>
                        <select id="sort" name="sort" onchange="this.form.submit()" class="rounded-lg border border-shop-line bg-white px-3 py-2 text-sm">
                            @foreach ($sorts as $value => $label)
                                <option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>

                @if ($vehicles->isEmpty())
                    <div class="rounded-2xl border border-dashed border-shop-line bg-white p-12 text-center">
                        <span class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
                            <x-icon name="car" class="w-7 h-7" />
                        </span>
                        <h3 class="text-lg font-bold text-shop-ink">Nothing Matches Those Filters</h3>
                        <p class="mx-auto mt-1.5 max-w-sm text-sm text-shop-muted">Try widening the price cap or clearing the body type. New stock lands most weeks.</p>
                        <a href="{{ route('shop.inventory') }}" class="mt-5 inline-flex rounded-lg bg-brand-700 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-800">Show Everything</a>
                    </div>
                @else
                    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($vehicles as $vehicle)
                            <a href="{{ route('shop.vehicle', $vehicle) }}"
                               class="group flex flex-col overflow-hidden rounded-2xl border border-shop-line bg-white transition-colors hover:border-brand-500">
                                <div class="relative aspect-[4/3] bg-gradient-to-br from-brand-100 to-brand-200">
                                    @if ($vehicle->primaryPhoto())
                                        <img src="{{ $vehicle->primaryPhoto() }}" alt="{{ $vehicle->title }}" class="h-full w-full object-cover">
                                    @else
                                        <span class="flex h-full w-full items-center justify-center text-brand-400">
                                            <x-icon name="car" class="w-14 h-14" />
                                        </span>
                                    @endif

                                    <span class="absolute left-3 top-3 rounded-full bg-white/95 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-brand-800">
                                        {{ $vehicle->condition_label }}
                                    </span>
                                    @if ($vehicle->status === 'pending')
                                        <span class="absolute right-3 top-3 rounded-full bg-amber-500 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-white">Sale Pending</span>
                                    @elseif ($vehicle->is_featured)
                                        <span class="absolute right-3 top-3 rounded-full bg-brand-700 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-white">Featured</span>
                                    @endif
                                </div>

                                <div class="flex flex-1 flex-col p-4">
                                    <h3 class="font-bold text-shop-ink group-hover:text-brand-700">{{ $vehicle->short_title }}</h3>
                                    <p class="mt-0.5 text-sm text-shop-muted">{{ $vehicle->trim ?: $vehicle->body_type }}</p>

                                    <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5 text-xs text-shop-muted">
                                        <div class="flex items-center gap-1.5"><x-icon name="clock" class="w-3.5 h-3.5" />{{ $vehicle->mileage_display }}</div>
                                        <div class="flex items-center gap-1.5"><x-icon name="bolt" class="w-3.5 h-3.5" />{{ $vehicle->fuel_type ?: 'Gasoline' }}</div>
                                        <div class="flex items-center gap-1.5"><x-icon name="refresh" class="w-3.5 h-3.5" />{{ $vehicle->drivetrain ?: 'FWD' }}</div>
                                        <div class="flex items-center gap-1.5"><x-icon name="tag" class="w-3.5 h-3.5" />#{{ $vehicle->stock_number }}</div>
                                    </dl>

                                    <div class="mt-auto flex items-end justify-between gap-2 pt-4">
                                        <div>
                                            <p class="text-xl font-black text-shop-ink">{{ $vehicle->price_display }}</p>
                                            @if ($vehicle->estimatedMonthly())
                                                <p class="text-[11px] text-shop-muted">Est. ${{ number_format($vehicle->estimatedMonthly()) }}/mo</p>
                                            @endif
                                        </div>
                                        <span class="inline-flex items-center gap-1 text-sm font-bold text-brand-700">
                                            View <x-icon name="chevron-right" class="w-4 h-4 transition-transform group-hover:translate-x-0.5" />
                                        </span>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    <div class="mt-8">{{ $vehicles->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.shop>
