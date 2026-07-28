<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CannedJob;
use App\Models\Setting;
use Illuminate\Http\Request;

/** The shop's standard jobs, priced once and quoted in one click. */
class CannedJobController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.canned-jobs.index', [
            'jobs' => CannedJob::search($request->string('q')->toString() ?: null)
                ->orderBy('category')->orderBy('position')->orderBy('name')
                ->paginate((int) config('dealership.rows_per_page', 25))
                ->withQueryString(),
            'filters' => $request->only('q'),
            'rate' => (int) (Setting::get('labour_rate_cents') ?: 12500),
        ]);
    }

    public function create()
    {
        return view('admin.canned-jobs.create', ['job' => new CannedJob(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $job = CannedJob::create($this->validated($request));

        return redirect()->route('canned-jobs.index')->with('status', 'Canned job saved.');
    }

    public function edit(CannedJob $cannedJob)
    {
        return view('admin.canned-jobs.edit', ['job' => $cannedJob]);
    }

    public function update(Request $request, CannedJob $cannedJob)
    {
        $cannedJob->update($this->validated($request));

        return redirect()->route('canned-jobs.index')->with('status', 'Canned job updated.');
    }

    public function destroy(CannedJob $cannedJob)
    {
        $cannedJob->delete();

        return redirect()->route('canned-jobs.index')->with('status', 'Canned job removed.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'labour_hours' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'labour_rate' => ['nullable', 'string', 'max:32'],
            'parts_price' => ['nullable', 'string', 'max:32'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'name' => $data['name'],
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            // Hundredths of an hour, so 1.8 stays exactly 1.8.
            'labour_hundredths' => (int) round((float) ($data['labour_hours'] ?? 0) * 100),
            'labour_rate_cents' => $this->cents($data['labour_rate'] ?? null),
            'parts_cents' => $this->cents($data['parts_price'] ?? null) ?? 0,
            'position' => (int) ($data['position'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function cents(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return (int) round((float) preg_replace('/[^0-9.\-]/', '', $value) * 100);
    }
}
