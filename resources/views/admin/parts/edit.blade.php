<x-layouts.app :title="$part->part_number">
    <x-page-header :title="$part->part_number" icon="box" :subtitle="$part->name"
        :back="['href' => route('parts.show', $part), 'label' => 'Part']" />
    <form method="POST" action="{{ route('parts.update', $part) }}">
        @csrf @method('PUT')
        @include('admin.parts._form')
        <div class="mt-6 flex items-center justify-between gap-2">
            <x-delete-button :action="route('parts.destroy', $part)" :name="$part->part_number" />
            <x-button type="submit" icon="check">Save Part</x-button>
        </div>
    </form>
</x-layouts.app>
