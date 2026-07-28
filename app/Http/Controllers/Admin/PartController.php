<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PartController extends Controller
{
    public function index(Request $request)
    {
        $parts = Part::with('suppliers')
            ->search($request->string('q')->toString())
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->boolean('active'), fn ($q) => $q->active())
            ->orderBy('part_number')
            ->paginate((int) config('dealership.rows_per_page', 25))
            ->withQueryString();

        // Computed after paging, because on-hand is a ledger sum and there is
        // no honest way to sort a page by it without summing the whole table.
        return view('admin.parts.index', [
            'parts' => $parts,
            'categories' => Part::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'filters' => $request->only(['q', 'category', 'active']),
            'lowCount' => Part::active()->whereNotNull('reorder_point')->get()->filter->isBelowReorderPoint()->count(),
        ]);
    }

    public function create()
    {
        return view('admin.parts.create', [
            'part' => new Part(['is_active' => true, 'is_stocked' => true, 'is_taxable' => true]),
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'links' => collect(),
        ]);
    }

    public function store(Request $request)
    {
        $part = Part::create($this->validated($request));
        $this->syncSuppliers($request, $part);

        // An opening count is a movement like any other, so the ledger starts
        // with a row that says where the stock came from.
        if ($request->filled('opening_qty') && (int) $request->input('opening_qty') !== 0) {
            $part->move('adjusted', (int) $request->input('opening_qty'), [
                'unit_cost_cents' => $part->cost_cents,
                'reason' => 'Opening count',
            ]);
        }

        return redirect()->route('parts.show', $part)->with('status', 'Part added.');
    }

    public function show(Part $part)
    {
        return view('admin.parts.show', [
            'part' => $part->load(['suppliers', 'supersededBy', 'supersedes']),
            'movements' => $part->movements()->with(['workOrder', 'user'])->limit(50)->get(),
        ]);
    }

    public function edit(Part $part)
    {
        return view('admin.parts.edit', [
            'part' => $part,
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'links' => $part->suppliers->keyBy('id'),
        ]);
    }

    public function update(Request $request, Part $part)
    {
        $part->update($this->validated($request, $part));
        $this->syncSuppliers($request, $part);

        return back()->with('status', 'Part saved.');
    }

    /** A stock correction, which is a ledger row and never an edit to a count. */
    public function adjust(Request $request, Part $part)
    {
        $data = $request->validate([
            'qty' => ['required', 'integer', 'not_in:0'],
            'kind' => ['required', Rule::in(['adjusted', 'returned', 'scrapped'])],
            'reason' => ['required', 'string', 'max:190'],
        ]);

        $qty = $data['kind'] === 'returned' ? abs($data['qty']) : $data['qty'];

        if ($data['kind'] === 'scrapped') {
            $qty = -abs($qty);
        }

        $part->move($data['kind'], $qty, ['reason' => $data['reason'], 'unit_cost_cents' => $part->cost_cents]);

        return back()->with('status', 'Stock adjusted. On hand is now '.$part->onHand().'.');
    }

    public function destroy(Part $part)
    {
        $part->delete();

        return redirect()->route('parts.index')->with('status', 'Part deleted.');
    }

    private function validated(Request $request, ?Part $part = null): array
    {
        return $request->validate([
            'part_number' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:190'],
            'category' => ['nullable', 'string', 'max:80'],
            'brand' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cost_cents' => ['nullable', 'integer', 'min:0'],
            'price_cents' => ['nullable', 'integer', 'min:0'],
            'core_charge_cents' => ['nullable', 'integer', 'min:0'],
            'bin_location' => ['nullable', 'string', 'max:40'],
            'reorder_point' => ['nullable', 'integer', 'min:0'],
            'reorder_qty' => ['nullable', 'integer', 'min:0'],
            'superseded_by_part_id' => ['nullable', 'integer', 'exists:parts,id', Rule::notIn([$part?->id])],
            'is_stocked' => ['nullable', 'boolean'],
            'is_taxable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]) + [
            'is_stocked' => $request->boolean('is_stocked'),
            'is_taxable' => $request->boolean('is_taxable'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function syncSuppliers(Request $request, Part $part): void
    {
        $rows = collect($request->input('suppliers', []))
            ->filter(fn ($row) => ! empty($row['selected']))
            ->mapWithKeys(fn ($row, $id) => [(int) $id => [
                'supplier_part_number' => $row['supplier_part_number'] ?? null,
                'cost_cents' => ($row['cost_cents'] ?? '') === '' ? null : (int) $row['cost_cents'],
                'lead_time_days' => ($row['lead_time_days'] ?? '') === '' ? null : (int) $row['lead_time_days'],
                'is_preferred' => ! empty($row['is_preferred']),
                'last_quoted_at' => now(),
            ]])
            ->all();

        $part->suppliers()->sync($rows);
    }
}
