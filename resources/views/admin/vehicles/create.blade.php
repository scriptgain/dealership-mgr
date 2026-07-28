<x-layouts.app title="Add Vehicle">
    <x-page-header
        eyebrow="Vehicles"
        title="Add Vehicle"
        icon="wrench-screwdriver"
        subtitle="Register a vehicle so its service history builds up in one place."
        :back="['href' => route('vehicles.index'), 'label' => 'All Vehicles']" />

    @include('admin.vehicles._form')
</x-layouts.app>
