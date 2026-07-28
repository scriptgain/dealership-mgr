<x-layouts.app title="Service Reminders">
    <x-page-header eyebrow="Shop" title="Service Reminders" icon="bell"
        subtitle="Due by date or by odometer, with the date estimated from how each vehicle is driven.">
        <x-slot:primary>
            <x-button href="{{ route('reminders.create') }}" icon="plus">New Reminder</x-button>
        </x-slot:primary>
    </x-page-header>

    @if ($reminders->isEmpty() && $state === 'open')
        <x-card>
            <x-empty-state icon="bell" title="No Open Reminders"
                description="A reminder is what turns a one-off customer into a returning one. Set it by date for an annual check, or by odometer for an oil change, and the projected date comes from the vehicle's own mileage trend."
                :steps="[
                    'Create a reminder against a vehicle with a date or an odometer target.',
                    'Link a canned job so the quote is ready when they call.',
                    'Work the overdue list at the top first.',
                ]">
                <x-slot:action>
                    <x-button icon="plus" href="{{ route('reminders.create') }}">Create Your First Reminder</x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <x-data-surface>
            <x-slot:toolbar>
                <x-segmented label="Reminder State">
                    @foreach (['open' => 'Open', 'completed' => 'Completed', 'dismissed' => 'Dismissed'] as $key => $label)
                        <a href="{{ route('reminders.index', ['state' => $key]) }}"
                           class="vx-seg-item {{ $state === $key ? 'is-active' : '' }}"
                           @if ($state === $key) aria-current="page" @endif>
                            {{ $label }} <span class="vx-seg-count">{{ $counts[$key] }}</span>
                        </a>
                    @endforeach
                </x-segmented>
            </x-slot:toolbar>

            @if ($reminders->isEmpty())
                <x-empty-state icon="bell" title="Nothing Here"
                    description="No reminders in this state yet.">
                    <x-slot:action>
                        <x-button href="{{ route('reminders.index') }}" variant="secondary" size="sm">Show Open</x-button>
                    </x-slot:action>
                </x-empty-state>
            @else
                <x-table flush>
                    <thead>
                        <tr>
                            <th>Reminder</th>
                            <th>Vehicle</th>
                            <th>Customer</th>
                            <th>Due</th>
                            <th class="text-right">Miles To Go</th>
                            <th>Status</th>
                            <th class="text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reminders as $reminder)
                            <tr class="vx-rail vx-rail-{{ $reminder->statusBadge() }}">
                                <td>
                                    <a href="{{ route('reminders.edit', $reminder) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $reminder->name }}</a>
                                    @if ($reminder->cannedJob)
                                        <span class="block text-xs text-slate-500">{{ $reminder->cannedJob->name }} at <x-money :cents="$reminder->cannedJob->totalCents()" /></span>
                                    @endif
                                </td>
                                <td class="text-slate-600">
                                    @if ($reminder->vehicle)
                                        <a href="{{ route('vehicles.show', $reminder->vehicle) }}" class="hover:text-brand-700">{{ $reminder->vehicle->name }}</a>
                                    @else
                                        No Vehicle
                                    @endif
                                </td>
                                <td class="text-slate-600">{{ $reminder->customer?->name ?? '-' }}</td>
                                <td class="text-slate-600">
                                    @if ($reminder->effectiveDueDate())
                                        {{ $reminder->effectiveDueDate()->format(config('dealership.date_format', 'M j, Y')) }}
                                        @if (! $reminder->due_on)
                                            <span class="block text-xs text-slate-500">projected</span>
                                        @endif
                                    @elseif ($reminder->due_at_miles)
                                        <span class="text-xs text-slate-500">at {{ number_format($reminder->due_at_miles) }} mi</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="tabular text-right text-slate-700">
                                    {{ $reminder->milesRemaining() !== null ? number_format($reminder->milesRemaining()) : '-' }}
                                </td>
                                <td><x-badge :color="$reminder->statusBadge()" dot>{{ $reminder->statusLabel() }}</x-badge></td>
                                <td class="text-right">
                                    @if (in_array($reminder->status, ['due', 'notified'], true))
                                        <div class="flex items-center justify-end gap-1">
                                            <form method="POST" action="{{ route('reminders.complete', $reminder) }}">
                                                @csrf
                                                <x-button type="submit" variant="secondary" size="sm">Done</x-button>
                                            </form>
                                            <form method="POST" action="{{ route('reminders.dismiss', $reminder) }}">
                                                @csrf
                                                <x-button type="submit" variant="ghost" size="sm">Dismiss</x-button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            @endif
        </x-data-surface>

        <div class="mt-6">{{ $reminders->links() }}</div>
    @endif
</x-layouts.app>
