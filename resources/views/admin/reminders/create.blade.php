<x-layouts.app title="New Reminder">
    <x-page-header eyebrow="Reminders" title="New Reminder" icon="bell"
        subtitle="Bring a vehicle back when it is actually due."
        :back="['href' => route('reminders.index'), 'label' => 'All Reminders']" />
    @include('admin.reminders._form')
</x-layouts.app>
