<form method="POST" action="{{ $vehicle->exists ? route('vehicles.update', $vehicle) : route('vehicles.store') }}" class="space-y-6">
    @csrf
    @if ($vehicle->exists) @method('PUT') @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-card title="Vehicle" subtitle="Enough to identify it on the lot and on the invoice.">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-6">
                    <x-field label="Year" for="year" :error="$errors->first('year')" class="sm:col-span-2">
                        <x-input id="year" name="year" type="number" min="1900" max="{{ date('Y') + 2 }}"
                            :value="old('year', $vehicle->year)" placeholder="2018" autofocus />
                    </x-field>
                    <x-field label="Make" for="make" :error="$errors->first('make')" class="sm:col-span-2">
                        <x-input id="make" name="make" :value="old('make', $vehicle->make)" placeholder="Chevrolet" />
                    </x-field>
                    <x-field label="Model" for="model" :error="$errors->first('model')" class="sm:col-span-2">
                        <x-input id="model" name="model" :value="old('model', $vehicle->model)" placeholder="Silverado 1500" />
                    </x-field>
                    <x-field label="Trim" for="trim" :error="$errors->first('trim')" class="sm:col-span-3">
                        <x-input id="trim" name="trim" :value="old('trim', $vehicle->trim)" placeholder="LT Crew Cab" />
                    </x-field>
                    <x-field label="Color" for="color" :error="$errors->first('color')" class="sm:col-span-3">
                        <x-input id="color" name="color" :value="old('color', $vehicle->color)" placeholder="Summit White" />
                    </x-field>
                </div>
            </x-card>

            <x-card title="Identification" subtitle="The VIN is what parts lookups and warranty claims need.">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-6">
                    <x-field label="VIN" for="vin" :error="$errors->first('vin')" class="sm:col-span-4"
                        hint="17 characters on anything built since 1981.">
                        <x-input id="vin" name="vin" :value="old('vin', $vehicle->vin)" maxlength="32"
                            class="font-mono uppercase" placeholder="1GCUYDED5KZ123456" />
                    </x-field>
                    <x-field label="Plate" for="plate" :error="$errors->first('plate')" class="sm:col-span-2">
                        <x-input id="plate" name="plate" :value="old('plate', $vehicle->plate)" maxlength="32" class="uppercase" placeholder="ABC1234" />
                    </x-field>
                    <x-field label="Plate State" for="plate_state" :error="$errors->first('plate_state')" class="sm:col-span-2">
                        <x-input id="plate_state" name="plate_state" :value="old('plate_state', $vehicle->plate_state)" maxlength="8" class="uppercase" placeholder="AZ" />
                    </x-field>
                </div>
            </x-card>

            <x-card title="Drivetrain" subtitle="What the technician needs before ordering parts.">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-6">
                    <x-field label="Engine" for="engine" :error="$errors->first('engine')" class="sm:col-span-3">
                        <x-input id="engine" name="engine" :value="old('engine', $vehicle->engine)" placeholder="5.3L V8" />
                    </x-field>
                    <x-field label="Transmission" for="transmission" :error="$errors->first('transmission')" class="sm:col-span-3">
                        <x-input id="transmission" name="transmission" :value="old('transmission', $vehicle->transmission)" placeholder="8-Speed Automatic" />
                    </x-field>
                    <x-field label="Drivetrain" for="drivetrain" :error="$errors->first('drivetrain')" class="sm:col-span-2">
                        <x-select id="drivetrain" name="drivetrain">
                            <option value="">Not Recorded</option>
                            @foreach (['FWD', 'RWD', 'AWD', '4WD'] as $dt)
                                <option value="{{ $dt }}" @selected(old('drivetrain', $vehicle->drivetrain) === $dt)>{{ $dt }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                </div>
            </x-card>

            <x-card title="Notes" subtitle="Anything the next technician should know before they start.">
                <x-field label="Notes" for="notes" :error="$errors->first('notes')">
                    <textarea id="notes" name="notes" rows="4"
                        class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500"
                        placeholder="Aftermarket alarm, kill switch under the dash. Customer supplies own oil filter.">{{ old('notes', $vehicle->notes) }}</textarea>
                </x-field>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Owner">
                <x-field label="Customer" for="customer_id" :error="$errors->first('customer_id')">
                    <x-select id="customer_id" name="customer_id">
                        <option value="">No Customer</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((int) old('customer_id', $vehicle->customer_id) === $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </x-select>
                </x-field>
            </x-card>

            <x-card title="Odometer" subtitle="Recorded with its date so service intervals can be projected.">
                <div class="space-y-5">
                    <x-field label="Mileage" for="mileage" :error="$errors->first('mileage')">
                        <x-input id="mileage" name="mileage" type="number" min="0" :value="old('mileage', $vehicle->mileage)" placeholder="84500" />
                    </x-field>
                    <x-field label="Read On" for="mileage_read_on" :error="$errors->first('mileage_read_on')"
                        hint="Defaults to today when you enter a mileage.">
                        <x-input id="mileage_read_on" name="mileage_read_on" type="date"
                            :value="old('mileage_read_on', $vehicle->mileage_read_on?->format('Y-m-d'))" />
                    </x-field>
                </div>
            </x-card>

            <x-card title="Status">
                <x-toggle name="is_active" :checked="old('is_active', $vehicle->is_active ?? true)"
                    label="Active"
                    description="Turn off when a customer sells the vehicle. Its service history is kept either way." />
            </x-card>
        </div>
    </div>

    <div class="sticky bottom-0 z-20 -mx-4 flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3 shadow-[0_-4px_12px_-6px_rgba(15,23,42,0.15)] sm:-mx-6 sm:px-6">
        <x-button variant="secondary" href="{{ $vehicle->exists ? route('vehicles.show', $vehicle) : route('vehicles.index') }}">Cancel</x-button>
        <x-button type="submit" icon="check">{{ $vehicle->exists ? 'Save Changes' : 'Add Vehicle' }}</x-button>
    </div>
</form>
