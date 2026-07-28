<x-layouts.app title="New Inspection">
    <x-page-header
        eyebrow="Inspections"
        title="New Inspection"
        icon="eye"
        subtitle="Record what you found, price the fixes, then let the customer approve line by line."
        :back="['href' => route('inspections.index'), 'label' => 'All Inspections']" />

    @include('admin.inspections._form')
</x-layouts.app>
