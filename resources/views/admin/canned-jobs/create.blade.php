<x-layouts.app title="New Canned Job">
    <x-page-header eyebrow="Canned Jobs" title="New Canned Job" icon="bolt"
        subtitle="Price a job you do often, then quote it in one click."
        :back="['href' => route('canned-jobs.index'), 'label' => 'All Canned Jobs']" />
    @include('admin.canned-jobs._form')
</x-layouts.app>
