<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\InventoryVehicle;
use Illuminate\Http\Request;

/**
 * Public vehicle inventory: the browse and detail pages a shopper sees.
 *
 * Filter state lives entirely in the query string, so a filtered list is a real
 * shareable URL and the back button behaves.
 */
class InventoryController extends Controller
{
    public const SORTS = [
        'newest' => 'Newest Arrivals',
        'price_asc' => 'Price: Low To High',
        'price_desc' => 'Price: High To Low',
        'mileage_asc' => 'Lowest Mileage',
        'year_desc' => 'Newest Year',
    ];

    public function index(Request $request)
    {
        $query = InventoryVehicle::query()->listable();

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'make' => (string) $request->query('make', ''),
            'body_type' => (string) $request->query('body_type', ''),
            'condition' => (string) $request->query('condition', ''),
            'max_price' => (string) $request->query('max_price', ''),
            'sort' => array_key_exists($request->query('sort'), self::SORTS) ? $request->query('sort') : 'newest',
        ];

        if ($filters['q'] !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('make', 'like', $term)
                    ->orWhere('model', 'like', $term)
                    ->orWhere('trim', 'like', $term)
                    ->orWhere('stock_number', 'like', $term)
                    ->orWhere('body_type', 'like', $term);
            });
        }

        foreach (['make', 'body_type', 'condition'] as $field) {
            if ($filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }

        if (is_numeric($filters['max_price'])) {
            $query->where('price', '<=', (int) $filters['max_price'] * 100);
        }

        match ($filters['sort']) {
            'price_asc' => $query->orderByRaw('price is null, price asc'),
            'price_desc' => $query->orderByRaw('price is null, price desc'),
            'mileage_asc' => $query->orderBy('mileage'),
            'year_desc' => $query->orderByDesc('year'),
            default => $query->orderByDesc('is_featured')->orderByDesc('listed_on'),
        };

        return view('shop.inventory', [
            'vehicles' => $query->paginate(12)->withQueryString(),
            'filters' => $filters,
            'sorts' => self::SORTS,
            'makes' => InventoryVehicle::listable()->distinct()->orderBy('make')->pluck('make'),
            'bodyTypes' => InventoryVehicle::listable()->whereNotNull('body_type')->distinct()->orderBy('body_type')->pluck('body_type'),
            'conditions' => InventoryVehicle::CONDITIONS,
            'totalCount' => InventoryVehicle::listable()->count(),
        ]);
    }

    public function show(InventoryVehicle $vehicle)
    {
        abort_if($vehicle->status === 'sold' && ! request()->boolean('preview'), 404);

        return view('shop.vehicle', [
            'vehicle' => $vehicle,
            'similar' => InventoryVehicle::listable()
                ->where('id', '!=', $vehicle->id)
                ->where(fn ($q) => $q->where('body_type', $vehicle->body_type)->orWhere('make', $vehicle->make))
                ->orderByDesc('is_featured')
                ->limit(3)
                ->get(),
        ]);
    }
}
