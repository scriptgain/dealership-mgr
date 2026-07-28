<x-layouts.app :title="'Edit '.$vehicle->name">
    <x-page-header
        eyebrow="Vehicles"
        :title="'Edit '.$vehicle->name"
        icon="wrench-screwdriver"
        :subtitle="$vehicle->plate_label ?? $vehicle->vin"
        :back="['href' => route('vehicles.show', $vehicle), 'label' => 'Back To Vehicle']" />

    @include('admin.vehicles._form')
</x-layouts.app>
