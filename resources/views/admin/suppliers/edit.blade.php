<x-layouts.app :title="$supplier->name">
    <x-page-header :title="$supplier->name" icon="truck"
        :back="['href' => route('suppliers.show', $supplier), 'label' => 'Supplier']" />
    <form method="POST" action="{{ route('suppliers.update', $supplier) }}">
        @csrf @method('PUT')
        @include('admin.suppliers._form')
        <div class="mt-6 flex items-center justify-between gap-2">
            <x-delete-button :action="route('suppliers.destroy', $supplier)" :name="$supplier->name" />
            <x-button type="submit" icon="check">Save Supplier</x-button>
        </div>
    </form>
</x-layouts.app>
