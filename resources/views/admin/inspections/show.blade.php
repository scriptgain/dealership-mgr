<x-layouts.app :title="$inspection->number">
    <x-page-header
        eyebrow="Inspection"
        :title="$inspection->number"
        icon="eye"
        :subtitle="$inspection->vehicle?->name"
        :back="['href' => route('inspections.index'), 'label' => 'All Inspections']">
        <x-slot:meta>
            <x-badge :color="$inspection->statusBadge()" dot>{{ $inspection->statusLabel() }}</x-badge>
            @if ($inspection->customer)<x-badge color="neutral">{{ $inspection->customer->name }}</x-badge>@endif
            @if ($inspection->mileage)<x-badge color="neutral">{{ number_format($inspection->mileage) }} mi</x-badge>@endif
        </x-slot:meta>

        <x-slot:actions>
            @if (! $inspection->isSent())
                <form method="POST" action="{{ route('inspections.send', $inspection) }}">
                    @csrf
                    <x-button type="submit" size="sm" icon="envelope">Send To Customer</x-button>
                </form>
            @endif
            @if ($inspection->approvedNotYetBilled()->isNotEmpty())
                <form method="POST" action="{{ route('inspections.bill', $inspection) }}">
                    @csrf
                    <x-button type="submit" size="sm" icon="check">Add Approved To Repair Order</x-button>
                </form>
            @endif
            <x-button variant="secondary" size="sm" icon="edit" href="{{ route('inspections.edit', $inspection) }}">Edit</x-button>
            <x-delete-button :action="route('inspections.destroy', $inspection)" name="delete-inspection"
                label="Delete Inspection" title="Delete This Inspection?"
                message="This permanently removes the inspection, its findings, and its photos. Any work already added to a repair order stays. This cannot be undone." />
        </x-slot:actions>
    </x-page-header>

    @if (session('error'))
        <x-alert type="danger" class="mb-6">{{ session('error') }}</x-alert>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Findings" :value="$inspection->items->count()" icon="document" />
        <x-stat label="Approved" :value="$counts['approved']" :trend="$counts['pending'].' awaiting a decision'" icon="check-circle" />
        <x-stat label="Declined" :value="$counts['declined']" icon="x-circle" />
        <x-stat label="Approved Value" icon="credit-card"
            :value="\App\Support\Money::format($inspection->approvedTotalCents())"
            :trend="'of '.\App\Support\Money::format($inspection->recommendedTotalCents()).' recommended'" />
    </div>

    @if ($inspection->isSent())
        <x-card title="Customer Review Link" subtitle="Text or email this to the customer. No login needed." class="mt-6">
            <div class="flex flex-wrap items-center gap-3">
                <input type="text" readonly value="{{ $inspection->reviewUrl() }}"
                    onclick="this.select()"
                    class="min-w-0 flex-1 rounded-lg border-0 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700 ring-1 ring-inset ring-slate-200">
                <x-button variant="secondary" size="sm" href="{{ $inspection->reviewUrl() }}" target="_blank" rel="noopener" icon="external">Open</x-button>
            </div>
            <p class="mt-2 text-xs text-slate-500">
                Anyone with this link can see the inspection and answer it, so treat it like the customer's own copy.
            </p>
        </x-card>
    @endif

    <div class="mt-6 space-y-4">
        @foreach ($inspection->items as $item)
            <x-card>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        @if ($item->category)
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $item->category }}</p>
                        @endif
                        <h3 class="mt-0.5 font-semibold text-slate-900">{{ $item->name }}</h3>
                        @if ($item->finding)
                            <p class="mt-1 text-sm text-slate-600">{{ $item->finding }}</p>
                        @endif
                        @if ($item->measurement)
                            <p class="mt-1 text-xs text-slate-500">Measured: <span class="font-mono text-slate-700">{{ $item->measurement }}</span></p>
                        @endif
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <x-badge :color="$item->severityBadge()" dot>{{ $item->severityLabel() }}</x-badge>
                        @if ($item->isActionable())
                            <x-badge :color="$item->decisionBadge()">{{ $item->decisionLabel() }}</x-badge>
                        @endif
                        @if ($item->price_cents !== null)
                            <span class="text-sm font-semibold text-slate-900"><x-money :cents="$item->price_cents" /></span>
                        @endif
                        @if ($item->work_order_item_id)
                            <x-badge color="info">On Repair Order</x-badge>
                        @endif
                    </div>
                </div>

                @if ($item->photos->isNotEmpty())
                    <div class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-6">
                        @foreach ($item->photos as $photo)
                            <div class="group relative overflow-hidden rounded-lg ring-1 ring-inset ring-slate-200">
                                <a href="{{ route('inspections.photo', [$inspection, $photo]) }}" target="_blank" rel="noopener">
                                    <img src="{{ route('inspections.photo', [$inspection, $photo]) }}" alt="{{ $item->name }}"
                                         loading="lazy" class="h-20 w-full object-cover">
                                </a>
                                <form method="POST" action="{{ route('inspections.photos.destroy', [$inspection, $item, $photo]) }}"
                                      class="absolute right-1 top-1 opacity-0 transition group-hover:opacity-100">
                                    @csrf @method('DELETE')
                                    <button type="submit" title="Remove photo"
                                            class="rounded bg-white/90 px-1.5 py-0.5 text-[11px] font-medium text-rose-600 ring-1 ring-inset ring-rose-200">
                                        Remove
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('inspections.photos.store', [$inspection, $item]) }}"
                      enctype="multipart/form-data" class="mt-4 flex flex-wrap items-center gap-2">
                    @csrf
                    <input type="file" name="photos[]" accept="image/*" multiple capture="environment"
                        class="block w-full max-w-xs text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                    <x-button type="submit" variant="secondary" size="sm" icon="plus">Add Photos</x-button>
                </form>
            </x-card>
        @endforeach

        @if ($inspection->items->isEmpty())
            <x-card>
                <x-empty-state icon="document" title="No Findings Recorded"
                    description="Edit this inspection to load a checklist group or add findings by hand.">
                    <x-slot:action>
                        <x-button size="sm" icon="edit" href="{{ route('inspections.edit', $inspection) }}">Add Findings</x-button>
                    </x-slot:action>
                </x-empty-state>
            </x-card>
        @endif
    </div>

    @if ($inspection->isSent() && $inspection->status !== 'closed')
        <form method="POST" action="{{ route('inspections.close', $inspection) }}" class="mt-6">
            @csrf
            <x-button type="submit" variant="secondary" size="sm">Close Inspection</x-button>
        </form>
    @endif
</x-layouts.app>
