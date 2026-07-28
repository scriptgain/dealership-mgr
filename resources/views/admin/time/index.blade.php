<x-layouts.app title="Time Clock">
    <x-page-header title="Time Clock" icon="clock"
        subtitle="Who is on what right now, and what the day added up to." />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-card title="On The Clock Now" subtitle="One technician, one job. The clock refuses a second." flush>
                @if ($open->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-slate-500">Nobody is clocked on.</p>
                @else
                    <ul class="divide-y divide-slate-200">
                        @foreach ($open as $entry)
                            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                                <div class="min-w-0">
                                    <p class="font-medium text-slate-900">{{ $entry->user?->name }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $entry->activityLabel() }} on
                                        @if ($entry->workOrder)
                                            <a href="{{ route('work-orders.show', $entry->workOrder) }}" class="font-semibold text-brand-700 hover:underline">{{ $entry->workOrder->number }}</a>
                                        @else
                                            no job
                                        @endif
                                        since {{ $entry->started_at->format('H:i') }}
                                        @if (! $entry->started_at->isToday())
                                            <span class="font-semibold text-rose-700">({{ $entry->started_at->format('j M') }}, still running)</span>
                                        @endif
                                    </p>
                                </div>
                                <form method="POST" action="{{ route('time.clock-off', $entry) }}"
                                      class="flex shrink-0 flex-wrap items-end gap-2">
                                    @csrf
                                    <div class="w-32">
                                        <label class="mb-1 block text-xs font-medium text-slate-500">Billed (hundredths)</label>
                                        <x-input type="number" min="0" name="billed_hundredths"
                                                 value="{{ (int) round($entry->elapsedMinutes() / 60 * 100) }}" />
                                    </div>
                                    <x-button type="submit" size="sm" variant="secondary" icon="check">Clock Off</x-button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card :title="'Entries For '.$date->format('j F Y')" flush>
                <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-200 px-5 py-4">
                    <x-field label="Day">
                        <x-input type="date" name="date" value="{{ $date->toDateString() }}" />
                    </x-field>
                    <x-button type="submit" variant="secondary" icon="filter">Show</x-button>
                </form>

                @if ($entries->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-slate-500">Nothing logged.</p>
                @else
                    <x-table flush>
                        <thead>
                            <tr><th>Technician</th><th>Job</th><th>Activity</th><th class="text-right">Clock</th><th class="text-right">Billed</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($entries as $entry)
                                <tr>
                                    <td class="font-medium text-slate-900">{{ $entry->user?->name }}</td>
                                    <td class="text-slate-600">
                                        @if ($entry->workOrder)
                                            <a href="{{ route('work-orders.show', $entry->workOrder) }}" class="hover:text-brand-700">{{ $entry->workOrder->number }}</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-slate-600">{{ $entry->activityLabel() }}</td>
                                    <td class="text-right text-slate-600">
                                        {{ number_format($entry->elapsedMinutes() / 60, 2) }} hr
                                        @if ($entry->isOpen())
                                            <span class="block text-xs font-semibold text-emerald-700">running</span>
                                        @endif
                                    </td>
                                    <td class="text-right font-semibold text-slate-700">{{ $entry->hoursLabel() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Clock On">
                <form method="POST" action="{{ route('time.clock-on') }}" class="space-y-4">
                    @csrf
                    <x-field label="Technician" required :error="$errors->first('user_id')">
                        <x-select name="user_id" required>
                            @foreach ($technicians as $tech)
                                <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <x-field label="Work Order" required :error="$errors->first('work_order_id')">
                        <x-select name="work_order_id" required>
                            @foreach ($workOrders as $wo)
                                <option value="{{ $wo->id }}">{{ $wo->number }} - {{ $wo->title }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <x-field label="Activity">
                        <x-select name="activity">
                            @foreach (\App\Models\TimeEntry::ACTIVITIES as $activity)
                                <option value="{{ $activity }}">{{ \Illuminate\Support\Str::headline($activity) }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <x-field label="Note">
                        <x-input name="note" placeholder="What is being worked on" />
                    </x-field>
                    <x-button type="submit" class="w-full" icon="clock">Clock On</x-button>
                </form>
            </x-card>

            <x-card title="By Technician" :subtitle="$date->format('j F Y')" flush>
                @if ($byTech->isEmpty())
                    <p class="px-5 py-6 text-center text-sm text-slate-500">No hours logged.</p>
                @else
                    <ul class="divide-y divide-slate-200">
                        @foreach ($byTech as $row)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <span class="block text-sm font-medium text-slate-800">{{ $row['user']?->name }}</span>
                                    <span class="block text-xs text-slate-500">{{ number_format($row['clocked'] / 60, 2) }} hr on the clock</span>
                                </div>
                                <span class="shrink-0 text-sm font-semibold text-slate-700">{{ number_format($row['billed'] / 100, 2) }} hr billed</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="border-t border-slate-200 px-5 py-3 text-xs text-slate-500">
                        Clock time is what happened. Billed time is what the customer pays, and on a canned job those are deliberately different numbers.
                    </p>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.app>
