<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.purchase-orders.index', [
            'orders' => PurchaseOrder::with(['supplier', 'items'])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                ->when($request->filled('supplier'), fn ($q) => $q->where('supplier_id', $request->integer('supplier')))
                ->latest()
                ->paginate((int) config('dealership.rows_per_page', 25))
                ->withQueryString(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'filters' => $request->only(['status', 'supplier']),
        ]);
    }

    public function create(Request $request)
    {
        // Ordering from the shortage list is the common path, so seed the lines
        // from what is actually below its reorder point.
        $short = Part::active()->whereNotNull('reorder_point')->with('suppliers')
            ->get()->filter->isBelowReorderPoint();

        return view('admin.purchase-orders.create', [
            'order' => new PurchaseOrder(['status' => 'draft']),
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'parts' => Part::active()->orderBy('part_number')->get(),
            'workOrders' => WorkOrder::whereIn('status', ['scheduled', 'in_progress'])->latest()->limit(50)->get(),
            'shortages' => $short,
            'preselect' => $request->integer('part') ?: null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'work_order_id' => ['nullable', 'integer', 'exists:work_orders,id'],
            'expected_on' => ['nullable', 'date'],
            'supplier_reference' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.part_id' => ['required', 'integer', 'exists:parts,id'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.unit_cost_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        $order = PurchaseOrder::create([
            'supplier_id' => $data['supplier_id'],
            'work_order_id' => $data['work_order_id'] ?? null,
            'expected_on' => $data['expected_on'] ?? null,
            'supplier_reference' => $data['supplier_reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'ordered_by_user_id' => $request->user()->id,
            'status' => 'draft',
        ]);

        foreach ($data['lines'] as $line) {
            $part = Part::find($line['part_id']);

            $order->items()->updateOrCreate(
                ['part_id' => $part->id],
                [
                    'qty' => $line['qty'],
                    // Falls back to the supplier's own quoted cost, which is
                    // what a buyer means when they leave the field blank.
                    'unit_cost_cents' => $line['unit_cost_cents']
                        ?? $part->suppliers->firstWhere('id', $data['supplier_id'])?->pivot->cost_cents
                        ?? $part->cost_cents,
                ]
            );
        }

        return redirect()->route('purchase-orders.show', $order)->with('status', 'Purchase order drafted.');
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        return view('admin.purchase-orders.show', [
            'order' => $purchaseOrder->load(['supplier', 'workOrder', 'items.part']),
        ]);
    }

    public function place(PurchaseOrder $purchaseOrder)
    {
        try {
            $purchaseOrder->place();
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'Order placed with '.$purchaseOrder->supplier->name.'.');
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder)
    {
        try {
            $moved = $purchaseOrder->receive($request->input('received', []));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        if ($moved === 0) {
            return back()->withErrors(['status' => 'Nothing left to receive on this order.']);
        }

        $fresh = $purchaseOrder->fresh()->load('items');

        return back()->with('status', $fresh->isFullyReceived()
            ? 'Received in full and booked into stock.'
            : 'Part delivery booked in. '.$fresh->outstandingUnits().' units still outstanding.');
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'draft') {
            return back()->withErrors(['status' => 'Only a draft order can be deleted. Cancel it instead.']);
        }

        $purchaseOrder->delete();

        return redirect()->route('purchase-orders.index')->with('status', 'Purchase order deleted.');
    }
}
