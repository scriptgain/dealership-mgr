<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <x-card title="Supplier">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="Name" class="sm:col-span-2" required :error="$errors->first('name')">
                    <x-input name="name" value="{{ old('name', $supplier->name) }}" required />
                </x-field>
                <x-field label="Account Number">
                    <x-input name="account_number" value="{{ old('account_number', $supplier->account_number) }}" />
                </x-field>
                <x-field label="Contact Name">
                    <x-input name="contact_name" value="{{ old('contact_name', $supplier->contact_name) }}" />
                </x-field>
                <x-field label="Email" :error="$errors->first('email')">
                    <x-input type="email" name="email" value="{{ old('email', $supplier->email) }}" />
                </x-field>
                <x-field label="Phone">
                    <x-input name="phone" value="{{ old('phone', $supplier->phone) }}" />
                </x-field>
                <x-field label="Website" class="sm:col-span-2" :error="$errors->first('website')">
                    <x-input name="website" value="{{ old('website', $supplier->website) }}" placeholder="https://" />
                </x-field>
                <x-field label="Address" class="sm:col-span-2">
                    <textarea name="address" rows="3"
                        class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('address', $supplier->address) }}</textarea>
                </x-field>
            </div>
        </x-card>
    </div>

    <div class="space-y-6">
        <x-card title="Trading">
            <div class="space-y-4">
                <x-field label="Terms">
                    <x-input name="terms" value="{{ old('terms', $supplier->terms) }}" placeholder="Net 30" />
                </x-field>
                <x-field label="Typical Lead Time (days)"
                         hint="Used to warn when a job is promised sooner than the part can arrive.">
                    <x-input type="number" min="0" name="lead_time_days" value="{{ old('lead_time_days', $supplier->lead_time_days) }}" />
                </x-field>
                <x-field label="Notes">
                    <textarea name="notes" rows="4"
                        class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('notes', $supplier->notes) }}</textarea>
                </x-field>
                <x-toggle name="is_active" :checked="old('is_active', $supplier->is_active)" label="Active" />
            </div>
        </x-card>
    </div>
</div>
