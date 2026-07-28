<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.suppliers.index', [
            'suppliers' => Supplier::withCount('parts')
                ->search($request->string('q')->toString())
                ->when($request->boolean('active'), fn ($q) => $q->active())
                ->orderBy('name')
                ->paginate((int) config('dealership.rows_per_page', 25))
                ->withQueryString(),
            'filters' => $request->only(['q', 'active']),
        ]);
    }

    public function create()
    {
        return view('admin.suppliers.create', ['supplier' => new Supplier(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $supplier = Supplier::create($this->validated($request));

        return redirect()->route('suppliers.show', $supplier)->with('status', 'Supplier added.');
    }

    public function show(Supplier $supplier)
    {
        return view('admin.suppliers.show', [
            'supplier' => $supplier->load(['parts', 'purchaseOrders.items']),
            'orders' => $supplier->purchaseOrders()->with('items')->limit(12)->get(),
        ]);
    }

    public function edit(Supplier $supplier)
    {
        return view('admin.suppliers.edit', ['supplier' => $supplier]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($this->validated($request));

        return back()->with('status', 'Supplier saved.');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('status', 'Supplier deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'account_number' => ['nullable', 'string', 'max:60'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:190'],
            'address' => ['nullable', 'string', 'max:500'],
            'terms' => ['nullable', 'string', 'max:120'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
