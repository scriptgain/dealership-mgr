<form method="POST" action="{{ $reminder->exists ? route('reminders.update', $reminder) : route('reminders.store') }}" class="space-y-6">
    @csrf
    @if ($reminder->exists) @method('PUT') @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-card title="What And When" subtitle="Give it a date, an odometer target, or both.">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-field label="Vehicle" for="vehicle_id" required :error="$errors->first('vehicle_id')" class="sm:col-span-2">
                        <x-select id="vehicle_id" name="vehicle_id" required>
                            <option value="">Choose a vehicle</option>
                            @foreach ($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}" @selected((int) old('vehicle_id', $reminder->vehicle_id) === $vehicle->id)>
                                    {{ $vehicle->name }}{{ $vehicle->plate ? ' ('.strtoupper($vehicle->plate).')' : '' }}{{ $vehicle->customer ? ' - '.$vehicle->customer->name : '' }}
                                </option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <x-field label="Reminder" for="name" required :error="$errors->first('name')" class="sm:col-span-2">
                        <x-input id="name" name="name" :value="old('name', $reminder->name)" required placeholder="Oil and filter change due" />
                    </x-field>
                    <x-field label="Standard Job" for="canned_job_id" :error="$errors->first('canned_job_id')"
                        hint="Optional. Links the reminder to a priced job.">
                        <x-select id="canned_job_id" name="canned_job_id">
                            <option value="">No linked job</option>
                            @foreach ($jobs as $job)
                                <option value="{{ $job->id }}" @selected((int) old('canned_job_id', $reminder->canned_job_id) === $job->id)>{{ $job->name }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <x-field label="Due On" for="due_on" :error="$errors->first('due_on')">
                        <x-input id="due_on" name="due_on" type="date" :value="old('due_on', $reminder->due_on?->format('Y-m-d'))" />
                    </x-field>
                    <x-field label="Due At Odometer" for="due_at_miles" :error="$errors->first('due_at_miles')"
                        hint="We estimate the date from how the vehicle is driven.">
                        <x-input id="due_at_miles" name="due_at_miles" type="number" min="0" :value="old('due_at_miles', $reminder->due_at_miles)" placeholder="90000" />
                    </x-field>
                    <x-field label="Notes" for="notes" :error="$errors->first('notes')" class="sm:col-span-2">
                        <textarea id="notes" name="notes" rows="3"
                            class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('notes', $reminder->notes) }}</textarea>
                    </x-field>
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            @if ($reminder->exists && $reminder->vehicle)
                <x-card title="This Vehicle">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Odometer</dt>
                            <dd class="font-medium text-slate-900">{{ number_format((int) $reminder->vehicle->currentMileage()) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Average Use</dt>
                            <dd class="font-medium text-slate-900">
                                {{ $reminder->vehicle->milesPerDay() ? number_format($reminder->vehicle->milesPerDay() * 30).' mi/mo' : 'Not enough data' }}
                            </dd>
                        </div>
                        @if ($reminder->milesRemaining() !== null)
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Miles To Go</dt>
                                <dd class="font-medium text-slate-900">{{ number_format($reminder->milesRemaining()) }}</dd>
                            </div>
                        @endif
                        @if ($reminder->projectedDueDate())
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Projected</dt>
                                <dd class="font-medium text-slate-900">{{ $reminder->projectedDueDate()->format(config('dealership.date_format', 'M j, Y')) }}</dd>
                            </div>
                        @endif
                    </dl>
                </x-card>
            @endif
        </div>
    </div>

    <div class="sticky bottom-0 z-20 -mx-4 flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3 shadow-[0_-4px_12px_-6px_rgba(15,23,42,0.15)] sm:-mx-6 sm:px-6">
        <x-button variant="secondary" href="{{ route('reminders.index') }}">Cancel</x-button>
        <x-button type="submit" icon="check">{{ $reminder->exists ? 'Save Reminder' : 'Create Reminder' }}</x-button>
    </div>
</form>
