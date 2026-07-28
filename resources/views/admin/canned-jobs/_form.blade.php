<form method="POST" action="{{ $job->exists ? route('canned-jobs.update', $job) : route('canned-jobs.store') }}" class="space-y-6">
    @csrf
    @if ($job->exists) @method('PUT') @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-card title="The Job" subtitle="What you call it at the counter.">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-field label="Name" for="name" required :error="$errors->first('name')" class="sm:col-span-2">
                        <x-input id="name" name="name" :value="old('name', $job->name)" required autofocus placeholder="Front Brake Pads And Rotors" />
                    </x-field>
                    <x-field label="Category" for="category" :error="$errors->first('category')">
                        <x-input id="category" name="category" :value="old('category', $job->category)" placeholder="Brakes" />
                    </x-field>
                    <x-field label="List Order" for="position" :error="$errors->first('position')" hint="Lower shows first.">
                        <x-input id="position" name="position" type="number" min="0" :value="old('position', $job->position ?? 0)" />
                    </x-field>
                    <x-field label="Description" for="description" :error="$errors->first('description')" class="sm:col-span-2">
                        <textarea id="description" name="description" rows="3"
                            class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500"
                            placeholder="Premium pads, new rotors, hardware, brake fluid top off, road test.">{{ old('description', $job->description) }}</textarea>
                    </x-field>
                </div>
            </x-card>

            <x-card title="Pricing" subtitle="Book time times your rate, plus parts.">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <x-field label="Labour Hours" for="labour_hours" :error="$errors->first('labour_hours')" hint="Book time, e.g. 1.8">
                        <x-input id="labour_hours" name="labour_hours" inputmode="decimal"
                            :value="old('labour_hours', $job->exists ? $job->labourHours() : '')" placeholder="1.8" />
                    </x-field>
                    <x-field label="Rate Override" for="labour_rate" :error="$errors->first('labour_rate')" hint="Blank uses the shop rate.">
                        <x-input id="labour_rate" name="labour_rate" inputmode="decimal"
                            :value="old('labour_rate', $job->labour_rate_cents ? number_format($job->labour_rate_cents / 100, 2, '.', '') : '')" placeholder="125.00" />
                    </x-field>
                    <x-field label="Parts" for="parts_price" :error="$errors->first('parts_price')">
                        <x-input id="parts_price" name="parts_price" inputmode="decimal"
                            :value="old('parts_price', $job->parts_cents ? number_format($job->parts_cents / 100, 2, '.', '') : '')" placeholder="240.00" />
                    </x-field>
                </div>
                @if ($job->exists)
                    <p class="mt-4 text-sm text-slate-600">
                        Currently bills at <span class="font-semibold text-slate-900"><x-money :cents="$job->totalCents()" /></span>
                        ({{ $job->labourHours() }} h labour at <x-money :cents="$job->rateCents()" /> plus <x-money :cents="$job->parts_cents" /> parts).
                    </p>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Status">
                <x-toggle name="is_active" :checked="old('is_active', $job->is_active ?? true)" label="Active"
                    description="Inactive jobs stay on past repair orders but stop appearing when quoting." />
            </x-card>
        </div>
    </div>

    <div class="sticky bottom-0 z-20 -mx-4 flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3 shadow-[0_-4px_12px_-6px_rgba(15,23,42,0.15)] sm:-mx-6 sm:px-6">
        <x-button variant="secondary" href="{{ route('canned-jobs.index') }}">Cancel</x-button>
        <x-button type="submit" icon="check">{{ $job->exists ? 'Save Job' : 'Add Job' }}</x-button>
    </div>
</form>
