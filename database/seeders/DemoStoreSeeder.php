<?php

namespace Database\Seeders;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A believable demo service menu for Redline Auto Service, so a fresh install
 * has something to click through.
 *
 * Idempotent: keyed on slugs/codes/emails, so re-running it will not duplicate.
 * Safe to run on a demo host; pointless (but harmless) on a real shop.
 */
class DemoStoreSeeder extends Seeder
{
    public function run(): void
    {
        $this->identity();
        $collections = $this->collections();
        $this->products($collections);
        $this->discounts();
        $this->customers();
    }

    /**
     * Name the demo shop. Only writes while the setting is absent or still the
     * shipped product default, so a real shop's own name is never clobbered by
     * someone running the demo seeder on a live install by mistake.
     */
    private function identity(): void
    {
        $defaults = [
            'store_name' => 'Redline Auto Service',
            'store_tagline' => 'Honest Diagnostics, Photos With Every Estimate',
            'store_email' => 'service@redlineauto.example.com',
            'store_phone' => '(602) 555-0148',
            'store_address' => "1420 W Grant St\nPhoenix, AZ 85007",
        ];

        $existing = Setting::map();
        $productDefaults = ['DealershipMGR', config('dealership.store_name'), config('brand.name')];

        foreach ($defaults as $key => $value) {
            $current = $existing[$key] ?? null;

            if ($current === null || $current === '' || in_array($current, $productDefaults, true)) {
                Setting::put($key, $value);
            }
        }
    }

    private function collections(): array
    {
        $rows = [
            ['Maintenance', 'Scheduled service that keeps a vehicle out of the bay.', 0],
            ['Brakes & Suspension', 'Stopping and steering work, quoted per axle.', 1],
            ['Diagnostics & Electrical', 'Fault tracing, scan-tool work, batteries and charging.', 2],
            ['Tires & Alignment', 'Mounting, balancing, rotation and alignment.', 3],
        ];

        $out = [];
        foreach ($rows as [$name, $description, $position]) {
            $out[$name] = Collection::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => $description, 'position' => $position, 'is_active' => true]
            );
        }

        return $out;
    }

    private function products(array $collections): void
    {
        // [name, collection, excerpt, price cents, compare-at, options, stock]
        // Stock only matters on the parts lines; labour is unlimited.
        $rows = [
            ['Oil & Filter Change', 'Maintenance', 'Drain, new filter, top off fluids, and a courtesy inspection.', 5900, 7400, ['Oil' => ['Conventional', 'Synthetic Blend', 'Full Synthetic']], 0],
            ['30/60/90k Scheduled Service', 'Maintenance', 'Manufacturer interval service with fluid, filter and belt checks.', 34900, null, [], 0],
            ['Cabin & Engine Air Filter', 'Maintenance', 'Both filters replaced, cabin filter housing vacuumed out.', 8900, null, [], 24],
            ['Brake Pads & Rotors (Per Axle)', 'Brakes & Suspension', 'Premium pads, new rotors, hardware and a road test.', 41900, 47900, ['Axle' => ['Front', 'Rear']], 12],
            ['Brake Fluid Flush', 'Brakes & Suspension', 'Full system flush with fresh DOT 4 and a pressure bleed.', 14900, null, [], 0],
            ['Strut & Shock Replacement (Pair)', 'Brakes & Suspension', 'Complete assemblies installed, alignment checked after.', 68900, null, ['Position' => ['Front Pair', 'Rear Pair']], 6],
            ['Check-Engine Diagnostic', 'Diagnostics & Electrical', 'Scan, live data, and a written diagnosis. Waived if we do the repair.', 12900, null, [], 0],
            ['Battery & Charging Test', 'Diagnostics & Electrical', 'Load test the battery, alternator output and parasitic draw.', 4900, null, [], 0],
            ['AC Recharge & Leak Test', 'Diagnostics & Electrical', 'Evacuate, dye test for leaks, and recharge to spec.', 18900, 21900, ['Refrigerant' => ['R-134a', 'R-1234yf']], 0],
            ['Four-Wheel Alignment', 'Tires & Alignment', 'Camber, caster and toe set to factory spec with a printout.', 12900, null, [], 0],
            ['Tire Rotation & Balance', 'Tires & Alignment', 'Rotate, road-force balance, and reset tire pressures.', 6900, null, [], 0],
            ['Tire Mount & Balance (Each)', 'Tires & Alignment', 'Mount, balance, new valve stem, and disposal of the old tire.', 3400, null, ['Size' => ['16 in', '17 in', '18 in', '20 in']], 40],
        ];

        foreach ($rows as $i => [$name, $collectionName, $excerpt, $price, $compareAt, $options, $stock]) {
            $product = Product::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'excerpt' => $excerpt,
                    'description' => $excerpt.' '
                        .'Carried out by ASE-certified technicians and backed by a 24-month / 24,000-mile warranty '
                        .'on parts and labour. Loaner cars available on jobs kept overnight.',
                    'status' => 'active',
                    'vendor' => 'Redline Auto Service',
                    'product_type' => 'service',
                    'is_featured' => $i < 4,
                    'position' => $i,
                    'requires_shipping' => false,
                ]
            );

            if ($collection = $collections[$collectionName] ?? null) {
                $product->collections()->syncWithoutDetaching([$collection->id]);
            }

            if ($product->variants()->exists()) {
                continue;
            }

            if (! $options) {
                $product->variants()->create([
                    'sku' => $this->sku($name),
                    'price_cents' => $price,
                    'compare_at_price_cents' => $compareAt,
                    'cost_cents' => (int) round($price * 0.42),
                    'inventory_qty' => $stock,
                    'weight_grams' => 0,
                    'is_default' => true,
                    'position' => 0,
                ]);

                continue;
            }

            $axis = array_key_first($options);
            foreach (array_values($options[$axis]) as $position => $value) {
                $product->variants()->create([
                    'option1_name' => $axis,
                    'option1_value' => $value,
                    // Position is part of the SKU because short option values can
                    // truncate to the same three characters (R-134a / R-1234yf).
                    'sku' => $this->sku($name).'-'.strtoupper(substr(Str::slug($value), 0, 3)).($position + 1),
                    'price_cents' => $price + ($position * 2000),
                    'compare_at_price_cents' => $compareAt ? $compareAt + ($position * 2000) : null,
                    'cost_cents' => (int) round($price * 0.42),
                    // Vary stock so the low-stock and out-of-stock states are
                    // both visible on a demo instance.
                    'inventory_qty' => max(0, $stock - ($position * 4)),
                    'weight_grams' => 0,
                    'is_default' => $position === 0,
                    'position' => $position,
                ]);
            }
        }
    }

    private function discounts(): void
    {
        Discount::firstOrCreate(['code' => 'FIRSTVISIT10'], [
            'title' => 'First Visit — 10% Off Labour',
            'type' => 'percentage',
            'value' => 1000, // basis points
            'applies_to' => 'all',
            'once_per_customer' => true,
            'is_active' => true,
        ]);

        Discount::firstOrCreate(['code' => 'FLEET25'], [
            'title' => '$25 Off Fleet Service Over $250',
            'type' => 'fixed_amount',
            'value' => 2500,
            'applies_to' => 'all',
            'min_subtotal_cents' => 25000,
            'is_active' => true,
        ]);
    }

    private function customers(): void
    {
        foreach ([
            ['Ada', 'Whitfield', 'ada@example.com'],
            ['Theo', 'Ellery', 'theo@example.com'],
            ['Priya', 'Raman', 'priya@example.com'],
        ] as [$first, $last, $email]) {
            Customer::firstOrCreate(['email' => $email], [
                'first_name' => $first,
                'last_name' => $last,
                'accepts_marketing' => true,
            ]);
        }
    }

    private function sku(string $name): string
    {
        return 'RL-'.strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 6));
    }
}
