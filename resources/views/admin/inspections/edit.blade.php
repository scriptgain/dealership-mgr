<x-layouts.app :title="'Edit '.$inspection->number">
    <x-page-header
        eyebrow="Inspection"
        :title="'Edit '.$inspection->number"
        icon="eye"
        :subtitle="$inspection->vehicle?->name"
        :back="['href' => route('inspections.show', $inspection), 'label' => 'Back To Inspection']" />

    @if ($inspection->isSent())
        <x-alert type="warning" class="mb-6">
            This inspection is already with the customer. Editing a finding they have answered clears nothing,
            but changing a price after approval is worth a phone call rather than a silent edit.
        </x-alert>
    @endif

    @include('admin.inspections._form')
</x-layouts.app>
