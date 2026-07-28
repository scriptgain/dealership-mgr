<x-layouts.shop :title="'Inspection ' . $inspection->number">

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-12">
        <div class="mx-auto max-w-2xl">

            <div class="mb-8 text-center">
                <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-200">
                    <x-icon name="wrench-screwdriver" class="w-6 h-6" />
                </span>
                <h1 class="mt-4 text-2xl font-bold tracking-tight text-shop-ink sm:text-3xl">Your Vehicle Inspection</h1>
                <p class="mt-2 text-sm text-shop-muted">
                    {{ $inspection->vehicle->name }}
                    @if ($inspection->vehicle->plate_label)
                        <span class="mx-1 text-shop-line">&bull;</span>{{ $inspection->vehicle->plate_label }}
                    @endif
                    @if ($inspection->mileage)
                        <span class="mx-1 text-shop-line">&bull;</span>{{ number_format($inspection->mileage) }} mi
                    @endif
                </p>
                <p class="mt-1 text-xs text-shop-muted">
                    {{ $inspection->number }} from {{ $shopName }}
                    @if ($inspection->technician)
                        <span class="mx-1 text-shop-line">&bull;</span>Inspected by {{ $inspection->technician->name }}
                    @endif
                </p>
            </div>

            @if (session('status'))
                <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
            @endif
            @if (session('error'))
                <x-alert type="danger" class="mb-6">{{ session('error') }}</x-alert>
            @endif

            @if ($inspection->summary)
                <div class="mb-8 rounded-2xl bg-white p-5 ring-1 ring-inset ring-shop-line">
                    <p class="whitespace-pre-line text-sm text-shop-ink">{{ $inspection->summary }}</p>
                </div>
            @endif

            @if (! $inspection->isOpenForReview())
                <x-alert type="info" class="mb-8">
                    This inspection has been closed. Call {{ $shopName }} if you have questions about it.
                </x-alert>
            @endif

            {{-- Findings that need an answer. Everything here is priced. --}}
            @if ($actionable->isNotEmpty())
                <div class="mb-4 flex items-baseline justify-between gap-3">
                    <h2 class="text-lg font-semibold text-shop-ink">What We Found</h2>
                    <span class="text-xs text-shop-muted">{{ $actionable->count() }} {{ Str::plural('item', $actionable->count()) }} to review</span>
                </div>

                <div class="space-y-4">
                    @foreach ($actionable as $item)
                        <div class="overflow-hidden rounded-2xl bg-white ring-1 ring-inset ring-shop-line transition hover:ring-brand-200">
                            <div class="border-l-4 {{ $item->severity === 'urgent' ? 'border-rose-500' : 'border-amber-400' }} p-5">

                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        @if ($item->category)
                                            <p class="text-[11px] font-semibold uppercase tracking-wide text-shop-muted">{{ $item->category }}</p>
                                        @endif
                                        <h3 class="mt-0.5 font-semibold text-shop-ink">{{ $item->name }}</h3>
                                    </div>
                                    <x-badge :color="$item->severityBadge()" dot>{{ $item->severityLabel() }}</x-badge>
                                </div>

                                @if ($item->finding)
                                    <p class="mt-3 text-sm text-shop-ink">{{ $item->finding }}</p>
                                @endif

                                @if ($item->measurement)
                                    <p class="mt-2 text-xs text-shop-muted">Measured: <span class="font-mono font-medium text-shop-ink">{{ $item->measurement }}</span></p>
                                @endif

                                @if ($item->photos->isNotEmpty())
                                    <div class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-4">
                                        @foreach ($item->photos as $photo)
                                            @if ($photo->is_image)
                                                <a href="{{ route('shop.inspection.photo', [$inspection->review_token, $photo]) }}"
                                                   target="_blank" rel="noopener"
                                                   class="block overflow-hidden rounded-lg ring-1 ring-inset ring-shop-line transition hover:ring-brand-300">
                                                    <img src="{{ route('shop.inspection.photo', [$inspection->review_token, $photo]) }}"
                                                         alt="Photo of {{ $item->name }}" loading="lazy"
                                                         class="h-20 w-full object-cover sm:h-24">
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif

                                <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-shop-line pt-4">
                                    <p class="text-sm">
                                        <span class="text-shop-muted">To put right:</span>
                                        <span class="ml-1 font-semibold text-shop-ink"><x-money :cents="$item->price_cents" /></span>
                                    </p>

                                    @if ($item->work_order_item_id)
                                        <x-badge color="info">Work Started</x-badge>
                                    @elseif ($item->decision !== 'pending')
                                        <div class="flex items-center gap-2">
                                            <x-badge :color="$item->decisionBadge()" dot>{{ $item->decisionLabel() }}</x-badge>
                                            @if ($inspection->isOpenForReview())
                                                <form method="POST" action="{{ route('shop.inspection.decide', [$inspection->review_token, $item]) }}">
                                                    @csrf
                                                    <input type="hidden" name="decision" value="{{ $item->decision === 'approved' ? 'declined' : 'approved' }}">
                                                    <button type="submit" class="text-xs font-medium text-brand-700 underline hover:text-brand-800">
                                                        Change to {{ $item->decision === 'approved' ? 'declined' : 'approved' }}
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    @elseif ($inspection->isOpenForReview())
                                        <div class="flex items-center gap-2">
                                            <form method="POST" action="{{ route('shop.inspection.decide', [$inspection->review_token, $item]) }}">
                                                @csrf
                                                <input type="hidden" name="decision" value="declined">
                                                <x-button type="submit" variant="secondary" size="sm">Not Now</x-button>
                                            </form>
                                            <form method="POST" action="{{ route('shop.inspection.decide', [$inspection->review_token, $item]) }}">
                                                @csrf
                                                <input type="hidden" name="decision" value="approved">
                                                <x-button type="submit" size="sm" icon="check">Approve</x-button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @php $stillPending = $actionable->where('decision', 'pending')->whereNull('work_order_item_id'); @endphp

                @if ($inspection->isOpenForReview() && $stillPending->count() > 1)
                    <form method="POST" action="{{ route('shop.inspection.approve-all', $inspection->review_token) }}" class="mt-6">
                        @csrf
                        <x-button type="submit" class="w-full justify-center" icon="check">
                            Approve Everything ({{ $stillPending->count() }} items, <x-money :cents="$stillPending->sum('price_cents')" />)
                        </x-button>
                    </form>
                @endif

                <div class="mt-6 rounded-2xl bg-brand-50 p-5 ring-1 ring-inset ring-brand-200">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-medium text-brand-900">Approved so far</span>
                        <span class="text-lg font-bold text-brand-900"><x-money :cents="$inspection->approvedTotalCents()" /></span>
                    </div>
                    <p class="mt-1 text-xs text-brand-800">
                        You are only charged for what you approve. Nothing else gets done.
                    </p>
                </div>
            @endif

            {{-- The reassuring half. A customer who only sees problems assumes an upsell. --}}
            @if ($passed->isNotEmpty())
                <div class="mt-10">
                    <h2 class="mb-3 text-lg font-semibold text-shop-ink">Also Checked</h2>
                    <div class="overflow-hidden rounded-2xl bg-white ring-1 ring-inset ring-shop-line">
                        <ul class="divide-y divide-shop-line">
                            @foreach ($passed as $item)
                                <li class="flex items-center justify-between gap-3 px-5 py-3">
                                    <span class="min-w-0 text-sm text-shop-ink">
                                        {{ $item->name }}
                                        @if ($item->measurement)
                                            <span class="ml-2 font-mono text-xs text-shop-muted">{{ $item->measurement }}</span>
                                        @endif
                                    </span>
                                    <x-badge :color="$item->severityBadge()" dot>{{ $item->severityLabel() }}</x-badge>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <p class="mt-10 text-center text-xs text-shop-muted">
                Questions about anything here? Call {{ $shopName }} and quote {{ $inspection->number }}.
            </p>
        </div>
    </section>
</x-layouts.shop>
