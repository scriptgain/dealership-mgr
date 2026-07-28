<x-layouts.app title="New Supplier">
    <x-page-header title="New Supplier" icon="truck"
        :back="['href' => route('suppliers.index'), 'label' => 'Suppliers']" />
    <form method="POST" action="{{ route('suppliers.store') }}">
        @csrf
        @include('admin.suppliers._form')
        <div class="mt-6 flex justify-end gap-2">
            <x-button variant="secondary" href="{{ route('suppliers.index') }}">Cancel</x-button>
            <x-button type="submit" icon="check">Create Supplier</x-button>
        </div>
    </form>
</x-layouts.app>
