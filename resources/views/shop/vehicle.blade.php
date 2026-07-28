<x-layouts.shop :title="$vehicle->title">

    <div class="mx-auto {{ $maxWidth ?? 'max-w-7xl' }} px-5 py-6">
        <nav aria-label="Breadcrumb" class="mb-5 flex flex-wrap items-center gap-1.5 text-sm text-shop-muted">
            <a href="{{ route('shop.home') }}" class="hover:text-brand-700">Home</a>
            <x-icon name="chevron-right" class="w-3.5 h-3.5" />
            <a href="{{ route('shop.inventory') }}" class="hover:text-brand-700">Inventory</a>
            <x-icon name="chevron-right" class="w-3.5 h-3.5" />
            <span class="text-shop-ink">{{ $vehicle->short_title }}</span>
        </nav>

        <div class="grid gap-8 lg:grid-cols-[1.4fr_1fr]">

            {{-- Gallery + details --}}
            <div class="space-y-6">
                <div class="overflow-hidden rounded-2xl border border-shop-line bg-white">
                    <div class="relative aspect-[16/10] bg-gradient-to-br from-brand-100 to-brand-200">
                        @if ($vehicle->primaryPhoto())
                            <img src="{{ $vehicle->primaryPhoto() }}" alt="{{ $vehicle->title }}" class="h-full w-full object-cover">
                        @else
                            <span class="flex h-full w-full items-center justify-center text-brand-400">
                                <x-icon name="car" class="w-24 h-24" />
                            </span>
                        @endif
                        <span class="absolute left-4 top-4 rounded-full bg-white/95 px-3 py-1 text-xs font-bold uppercase tracking-wide text-brand-800">{{ $vehicle->condition_label }}</span>
                        @if ($vehicle->status === 'pending')
                            <span class="absolute right-4 top-4 rounded-full bg-amber-500 px-3 py-1 text-xs font-bold uppercase tracking-wide text-white">Sale Pending</span>
                        @endif
                    </div>
                </div>

                <div class="rounded-2xl border border-shop-line bg-white p-6">
                    <h2 class="text-lg font-bold text-shop-ink">Specifications</h2>
                    <hr class="my-4 border-shop-line">
                    <dl class="grid gap-x-8 gap-y-3 sm:grid-cols-2">
                        @foreach ([
                            'Stock Number' => '#'.$vehicle->stock_number,
                            'VIN' => $vehicle->vin ?: 'Available On Request',
                            'Mileage' => $vehicle->mileage_display,
                            'Body Type' => $vehicle->body_type,
                            'Engine' => $vehicle->engine,
                            'Transmission' => $vehicle->transmission,
                            'Drivetrain' => $vehicle->drivetrain,
                            'Fuel Type' => $vehicle->fuel_type,
                            'Exterior Color' => $vehicle->exterior_color,
                            'Interior Color' => $vehicle->interior_color,
                            'Doors' => $vehicle->doors,
                            'Seats' => $vehicle->seats,
                        ] as $label => $value)
                            @if (filled($value))
                                <div class="flex items-baseline justify-between gap-4 border-b border-dashed border-shop-line pb-2">
                                    <dt class="text-sm text-shop-muted">{{ $label }}</dt>
                                    <dd class="text-sm font-semibold text-shop-ink text-right">{{ $value }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>

                    @if ($vehicle->mpg_city || $vehicle->mpg_highway)
                        <div class="mt-5 flex flex-wrap gap-3">
                            @if ($vehicle->mpg_city)
                                <span class="rounded-xl bg-brand-50 px-4 py-2 text-sm"><span class="font-black text-brand-800">{{ $vehicle->mpg_city }}</span> <span class="text-shop-muted">MPG City</span></span>
                            @endif
                            @if ($vehicle->mpg_highway)
                                <span class="rounded-xl bg-brand-50 px-4 py-2 text-sm"><span class="font-black text-brand-800">{{ $vehicle->mpg_highway }}</span> <span class="text-shop-muted">MPG Highway</span></span>
                            @endif
                        </div>
                    @endif
                </div>

                @if (filled($vehicle->features))
                    <div class="rounded-2xl border border-shop-line bg-white p-6">
                        <h2 class="text-lg font-bold text-shop-ink">Features</h2>
                        <hr class="my-4 border-shop-line">
                        <ul class="grid gap-2 sm:grid-cols-2">
                            @foreach ($vehicle->features as $feature)
                                <li class="flex items-center gap-2 text-sm text-shop-ink">
                                    <x-icon name="check-circle" class="w-4 h-4 shrink-0 text-brand-600" />{{ $feature }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (filled($vehicle->description))
                    <div class="rounded-2xl border border-shop-line bg-white p-6">
                        <h2 class="text-lg font-bold text-shop-ink">About This Vehicle</h2>
                        <hr class="my-4 border-shop-line">
                        <p class="text-sm leading-relaxed text-shop-muted">{{ $vehicle->description }}</p>
                    </div>
                @endif
            </div>

            {{-- Sticky buy rail --}}
            <div class="lg:sticky lg:top-24 lg:self-start space-y-4">
                <div class="rounded-2xl border border-shop-line bg-white p-6">
                    <p class="text-xs font-bold uppercase tracking-wider text-shop-muted">{{ $vehicle->condition_label }}</p>
                    <h1 class="mt-1 text-2xl font-black leading-tight text-shop-ink">{{ $vehicle->short_title }}</h1>
                    @if ($vehicle->trim)
                        <p class="mt-1 text-shop-muted">{{ $vehicle->trim }}</p>
                    @endif

                    <hr class="my-5 border-shop-line">

                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <p class="text-3xl font-black text-shop-ink">{{ $vehicle->price_display }}</p>
                            @if ($vehicle->msrp_display && $vehicle->msrp > $vehicle->price)
                                <p class="text-sm text-shop-muted"><span class="line-through">{{ $vehicle->msrp_display }}</span> MSRP</p>
                            @endif
                        </div>
                        @if ($vehicle->estimatedMonthly())
                            <div class="text-right">
                                <p class="text-lg font-bold text-brand-700">${{ number_format($vehicle->estimatedMonthly()) }}<span class="text-sm font-semibold text-shop-muted">/mo</span></p>
                                <p class="text-[11px] text-shop-muted">Est. 72 Mo At 6.9% APR</p>
                            </div>
                        @endif
                    </div>

                    <p class="mt-2 text-[11px] leading-relaxed text-shop-muted">
                        Estimate only, assuming 10% down. Not a financing offer, and it excludes tax, title and fees.
                    </p>

                    <div class="mt-5 space-y-2.5">
                        <a href="{{ route('shop.request') }}?vehicle={{ $vehicle->stock_number }}"
                           class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-700 px-5 py-3 font-bold text-white transition hover:bg-brand-800">
                            <x-icon name="car" class="w-4 h-4" />Book A Test Drive
                        </a>
                        <a href="{{ route('shop.help') }}"
                           class="flex w-full items-center justify-center gap-2 rounded-xl border border-shop-line px-5 py-3 font-bold text-shop-ink transition hover:border-brand-500 hover:text-brand-700">
                            <x-icon name="envelope" class="w-4 h-4" />Ask About This Vehicle
                        </a>
                    </div>
                </div>

                <div class="rounded-2xl border border-shop-line bg-brand-50 p-5">
                    <h2 class="text-sm font-bold text-shop-ink">Every Vehicle Includes</h2>
                    <ul class="mt-3 space-y-2 text-sm text-shop-muted">
                        @foreach (['Multi-Point Inspection In Our Own Shop', 'Full History Report On Request', 'Independent Inspection Welcome', 'Trade-Ins Appraised While You Wait'] as $promise)
                            <li class="flex items-start gap-2"><x-icon name="check" class="mt-0.5 w-4 h-4 shrink-0 text-brand-600" />{{ $promise }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        @if ($similar->isNotEmpty())
            <hr class="my-10 border-shop-line">
            <h2 class="mb-5 text-lg font-bold text-shop-ink">Similar Vehicles</h2>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($similar as $other)
                    <a href="{{ route('shop.vehicle', $other) }}" class="group flex gap-4 rounded-2xl border border-shop-line bg-white p-4 transition-colors hover:border-brand-500">
                        <span class="flex h-16 w-20 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-100 to-brand-200 text-brand-400">
                            <x-icon name="car" class="w-8 h-8" />
                        </span>
                        <div class="min-w-0">
                            <p class="truncate font-bold text-shop-ink group-hover:text-brand-700">{{ $other->short_title }}</p>
                            <p class="truncate text-xs text-shop-muted">{{ $other->mileage_display }} &bull; {{ $other->drivetrain ?: $other->body_type }}</p>
                            <p class="mt-1 font-black text-shop-ink">{{ $other->price_display }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.shop>
