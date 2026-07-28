<x-layouts.app title="New Part">
    <x-page-header title="New Part" icon="box"
        :back="['href' => route('parts.index'), 'label' => 'Parts']" />
    <form method="POST" action="{{ route('parts.store') }}">
        @csrf
        @include('admin.parts._form')
        <div class="mt-6 flex justify-end gap-2">
            <x-button variant="secondary" href="{{ route('parts.index') }}">Cancel</x-button>
            <x-button type="submit" icon="check">Create Part</x-button>
        </div>
    </form>
</x-layouts.app>
