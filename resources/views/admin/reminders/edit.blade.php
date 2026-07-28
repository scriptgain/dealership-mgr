<x-layouts.app title="Edit Reminder">
    <x-page-header eyebrow="Reminder" :title="$reminder->name" icon="bell"
        :subtitle="$reminder->vehicle?->name"
        :back="['href' => route('reminders.index'), 'label' => 'All Reminders']" />
    @include('admin.reminders._form')
</x-layouts.app>
