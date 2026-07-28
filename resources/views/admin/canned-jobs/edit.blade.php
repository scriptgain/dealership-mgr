<x-layouts.app :title="'Edit '.$job->name">
    <x-page-header eyebrow="Canned Job" :title="'Edit '.$job->name" icon="bolt"
        :subtitle="$job->category"
        :back="['href' => route('canned-jobs.index'), 'label' => 'All Canned Jobs']" />
    @include('admin.canned-jobs._form')
</x-layouts.app>
