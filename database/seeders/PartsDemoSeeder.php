<?php

namespace Database\Seeders;

use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;

/**
 * The parts room and the clock at Redline Auto Service.
 *
 * Chosen so the screens show the arguments a shop actually has: a part below
 * its reorder point with stock already on order, a superseded number that must
 * still be findable, a part delivery that came up short, and a technician who
 * went home without clocking off.
 */
class PartsDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Part::exists()) {
            return;
        }

        $suppliers = collect([
            ['name' => 'Cordwainer Motor Factors', 'account' => 'RED-4471', 'contact' => 'Nell Ashby', 'terms' => 'Net 30', 'lead' => 1,
                'notes' => 'Twice-daily van. Anything ordered before 10am is here by lunch.'],
            ['name' => 'Halvorsen Parts Direct', 'account' => 'RAS-88120', 'contact' => 'Grigor Halvorsen', 'terms' => 'Net 15', 'lead' => 3],
            ['name' => 'Copperline OE Supply', 'account' => 'C-2209', 'contact' => 'Winsome Etuk', 'terms' => 'Prepay', 'lead' => 5,
                'notes' => 'Dealer-only parts. Expensive, but the only source for the German stuff.'],
            ['name' => 'Thistlewood Tyre And Rubber', 'account' => 'TTR-661', 'contact' => 'Bev Okafor', 'terms' => 'Net 30', 'lead' => 2],
        ])->map(fn (array $s) => Supplier::create([
            'name' => $s['name'],
            'account_number' => $s['account'],
            'contact_name' => $s['contact'],
            'email' => \Illuminate\Support\Str::slug($s['contact'], '.').'@'.\Illuminate\Support\Str::slug($s['name']).'.test',
            'phone' => '(602) 555-0'.random_int(300, 399),
            'terms' => $s['terms'],
            'lead_time_days' => $s['lead'],
            'notes' => $s['notes'] ?? null,
            'is_active' => true,
        ]));

        [$cordwainer, $halvorsen, $copperline, $thistlewood] = $suppliers->all();

        $catalogue = collect([
            ['pn' => 'BP-2214', 'name' => 'Front Brake Pad Set', 'cat' => 'Brakes', 'brand' => 'Ferodo', 'cost' => 3850, 'price' => 8900, 'bin' => 'B-14-3', 'min' => 4, 'reorder' => 8, 'open' => 9],
            ['pn' => 'BD-5560', 'name' => 'Front Brake Disc, Vented', 'cat' => 'Brakes', 'brand' => 'Brembo', 'cost' => 6200, 'price' => 13900, 'bin' => 'B-14-4', 'min' => 4, 'reorder' => 6, 'open' => 2, 'core' => 0],
            ['pn' => 'OF-1105', 'name' => 'Oil Filter, Spin-On', 'cat' => 'Service', 'brand' => 'Mann', 'cost' => 620, 'price' => 1650, 'bin' => 'A-02-1', 'min' => 20, 'reorder' => 40, 'open' => 46],
            ['pn' => 'AF-3320', 'name' => 'Engine Air Filter', 'cat' => 'Service', 'brand' => 'Mann', 'cost' => 940, 'price' => 2450, 'bin' => 'A-02-4', 'min' => 12, 'reorder' => 24, 'open' => 31],
            ['pn' => 'CF-7781', 'name' => 'Cabin Filter, Charcoal', 'cat' => 'Service', 'brand' => 'Bosch', 'cost' => 1180, 'price' => 3200, 'bin' => 'A-03-1', 'min' => 10, 'reorder' => 20, 'open' => 7],
            ['pn' => 'SP-9040', 'name' => 'Iridium Spark Plug', 'cat' => 'Ignition', 'brand' => 'NGK', 'cost' => 890, 'price' => 2150, 'bin' => 'C-08-2', 'min' => 16, 'reorder' => 32, 'open' => 44],
            ['pn' => 'ALT-4412', 'name' => 'Alternator, Remanufactured', 'cat' => 'Charging', 'brand' => 'Bosch', 'cost' => 18500, 'price' => 38900, 'bin' => 'D-01-1', 'min' => 1, 'reorder' => 2, 'open' => 1, 'core' => 7500],
            ['pn' => 'BAT-H6', 'name' => 'Battery, Group 48 AGM', 'cat' => 'Charging', 'brand' => 'Interstate', 'cost' => 12400, 'price' => 24900, 'bin' => 'D-04-1', 'min' => 3, 'reorder' => 6, 'open' => 5, 'core' => 2200],
            ['pn' => 'WB-2210', 'name' => 'Wheel Bearing And Hub Assembly', 'cat' => 'Suspension', 'brand' => 'Timken', 'cost' => 8900, 'price' => 19500, 'bin' => 'E-11-2', 'min' => 2, 'reorder' => 4, 'open' => 3],
            ['pn' => 'TR-P22565', 'name' => 'Tyre, 225/65 R17 All Season', 'cat' => 'Tyres', 'brand' => 'Michelin', 'cost' => 11200, 'price' => 21900, 'bin' => 'RACK-1', 'min' => 4, 'reorder' => 8, 'open' => 12],
            ['pn' => 'COOL-5L', 'name' => 'Coolant, Long Life, 5L', 'cat' => 'Fluids', 'brand' => 'Prestone', 'cost' => 1850, 'price' => 4200, 'bin' => 'F-01-1', 'min' => 6, 'reorder' => 12, 'open' => 14],
            ['pn' => 'ATF-1L', 'name' => 'Automatic Transmission Fluid, 1L', 'cat' => 'Fluids', 'brand' => 'Castrol', 'cost' => 1420, 'price' => 3400, 'bin' => 'F-01-3', 'min' => 8, 'reorder' => 16, 'open' => 19],
            ['pn' => 'WIP-24', 'name' => 'Wiper Blade, 24 Inch', 'cat' => 'Service', 'brand' => 'Bosch', 'cost' => 780, 'price' => 2100, 'bin' => 'A-05-2', 'min' => 10, 'reorder' => 20, 'open' => 26],
            ['pn' => 'SER-6PK', 'name' => 'Serpentine Belt, 6 Rib', 'cat' => 'Engine', 'brand' => 'Gates', 'cost' => 2650, 'price' => 6400, 'bin' => 'C-02-3', 'min' => 3, 'reorder' => 6, 'open' => 2],
            ['pn' => 'TB-KIT-9', 'name' => 'Timing Belt Kit With Water Pump', 'cat' => 'Engine', 'brand' => 'Gates', 'cost' => 21400, 'price' => 44900, 'bin' => 'C-06-1', 'min' => 0, 'reorder' => 1, 'open' => 1, 'stocked' => false],
        ])->map(fn (array $p) => Part::create([
            'part_number' => $p['pn'],
            'name' => $p['name'],
            'category' => $p['cat'],
            'brand' => $p['brand'],
            'cost_cents' => $p['cost'],
            'price_cents' => $p['price'],
            'core_charge_cents' => ($p['core'] ?? 0) ?: null,
            'bin_location' => $p['bin'],
            'reorder_point' => $p['min'],
            'reorder_qty' => $p['reorder'],
            'is_stocked' => $p['stocked'] ?? true,
            'is_taxable' => true,
            'is_active' => true,
        ]));

        // Opening counts, through the ledger like everything else.
        $catalogue->each(function (Part $part, int $i) {
            $opening = [9, 2, 46, 31, 7, 44, 1, 5, 3, 12, 14, 19, 26, 2, 1][$i];

            $part->move('adjusted', $opening, [
                'unit_cost_cents' => $part->cost_cents,
                'reason' => 'Opening count',
            ]);
        });

        // A superseded number that must still be findable in old repair orders.
        $oldPads = Part::create([
            'part_number' => 'BP-2210',
            'name' => 'Front Brake Pad Set (Superseded)',
            'category' => 'Brakes',
            'brand' => 'Ferodo',
            'cost_cents' => 3600,
            'price_cents' => 8500,
            'bin_location' => 'B-14-3',
            'superseded_by_part_id' => $catalogue->firstWhere('part_number', 'BP-2214')->id,
            'is_stocked' => false,
            'is_active' => false,
        ]);
        $oldPads->move('adjusted', 0, ['reason' => 'Superseded by BP-2214, kept for history']);

        // Who carries what, at what price. The same part from three factors is
        // the everyday case, and which one you ring depends on the lead time.
        $links = [
            'BP-2214' => [[$cordwainer, 'FER-2214', 3850, 1, true], [$halvorsen, 'H-BP2214', 3620, 3, false]],
            'BD-5560' => [[$cordwainer, 'BRE-5560', 6400, 1, false], [$halvorsen, 'H-BD5560', 6200, 3, true]],
            'OF-1105' => [[$cordwainer, 'MAN-1105', 620, 1, true]],
            'AF-3320' => [[$cordwainer, 'MAN-3320', 940, 1, true]],
            'CF-7781' => [[$cordwainer, 'BOS-7781', 1180, 1, true], [$halvorsen, 'H-CF7781', 1090, 3, false]],
            'SP-9040' => [[$cordwainer, 'NGK-9040', 890, 1, true]],
            'ALT-4412' => [[$copperline, 'OE-ALT-4412', 18500, 5, true], [$halvorsen, 'H-ALT4412', 19900, 3, false]],
            'BAT-H6' => [[$cordwainer, 'INT-H6', 12400, 1, true]],
            'WB-2210' => [[$halvorsen, 'H-WB2210', 8900, 3, true], [$copperline, 'OE-WB-2210', 12400, 5, false]],
            'TR-P22565' => [[$thistlewood, 'MIC-22565', 11200, 2, true]],
            'COOL-5L' => [[$cordwainer, 'PRE-5L', 1850, 1, true]],
            'ATF-1L' => [[$cordwainer, 'CAS-ATF1', 1420, 1, true]],
            'WIP-24' => [[$cordwainer, 'BOS-W24', 780, 1, true]],
            'SER-6PK' => [[$cordwainer, 'GAT-6PK', 2650, 1, true]],
            'TB-KIT-9' => [[$copperline, 'OE-TB-9', 21400, 5, true], [$halvorsen, 'H-TBK9', 22900, 3, false]],
        ];

        foreach ($links as $partNumber => $rows) {
            $part = $catalogue->firstWhere('part_number', $partNumber);

            foreach ($rows as [$supplier, $theirNumber, $cost, $lead, $preferred]) {
                $part->suppliers()->attach($supplier->id, [
                    'supplier_part_number' => $theirNumber,
                    'cost_cents' => $cost,
                    'lead_time_days' => $lead,
                    'is_preferred' => $preferred,
                    'last_quoted_at' => now()->subDays(random_int(3, 60)),
                ]);
            }
        }

        // A received order, so the ledger has a real receipt in it.
        $received = PurchaseOrder::create([
            'supplier_id' => $cordwainer->id,
            'status' => 'ordered',
            'expected_on' => now()->subDays(9)->toDateString(),
            'ordered_at' => now()->subDays(11),
            'supplier_reference' => 'CMF-77120',
            'notes' => 'Weekly stock order.',
        ]);
        foreach ([['OF-1105', 24], ['AF-3320', 12], ['WIP-24', 10]] as [$pn, $qty]) {
            $part = $catalogue->firstWhere('part_number', $pn);
            $received->items()->create(['part_id' => $part->id, 'qty' => $qty, 'unit_cost_cents' => $part->cost_cents]);
        }
        $received->refresh()->load('items');
        $received->receive($received->items->mapWithKeys(fn ($i) => [$i->id => $i->qty])->all());

        // An order that came up short, which is the case worth seeing: part
        // delivered, still outstanding, and the shop knows exactly how many.
        $short = PurchaseOrder::create([
            'supplier_id' => $halvorsen->id,
            'status' => 'ordered',
            'expected_on' => now()->subDays(2)->toDateString(),
            'ordered_at' => now()->subDays(5),
            'supplier_reference' => 'HPD-33481',
            'notes' => 'Brake stock. Discs were on back order at the time of ordering.',
        ]);
        foreach ([['BD-5560', 6], ['CF-7781', 20]] as [$pn, $qty]) {
            $part = $catalogue->firstWhere('part_number', $pn);
            $short->items()->create(['part_id' => $part->id, 'qty' => $qty, 'unit_cost_cents' => $part->bestCostCents()]);
        }
        $short->refresh()->load('items');
        $short->receive([
            $short->items->first()->id => 2,
            $short->items->last()->id => 20,
        ]);

        // An open order, so a part below its reorder point is legitimately not
        // a panic: the stock is already coming.
        $open = PurchaseOrder::create([
            'supplier_id' => $cordwainer->id,
            'status' => 'ordered',
            'expected_on' => now()->addDay()->toDateString(),
            'ordered_at' => now()->subHours(6),
            'supplier_reference' => 'CMF-77455',
        ]);
        foreach ([['SER-6PK', 6], ['BP-2214', 8]] as [$pn, $qty]) {
            $part = $catalogue->firstWhere('part_number', $pn);
            $open->items()->create(['part_id' => $part->id, 'qty' => $qty, 'unit_cost_cents' => $part->bestCostCents()]);
        }

        // The clock. Closed entries yesterday, one still running from a tech
        // who went home without clocking off, which is what the screen is for.
        $techs = User::orderBy('id')->limit(3)->get();
        $jobs = WorkOrder::whereIn('status', ['scheduled', 'in_progress', 'completed'])->limit(6)->get();

        if ($techs->isNotEmpty() && $jobs->isNotEmpty()) {
            foreach ($jobs as $i => $job) {
                $tech = $techs[$i % $techs->count()];
                $started = now()->subDay()->setTime(8 + ($i % 6), ($i * 17) % 60);
                $minutes = [95, 140, 45, 210, 65, 120][$i % 6];

                TimeEntry::create([
                    'user_id' => $tech->id,
                    'work_order_id' => $job->id,
                    'started_at' => $started,
                    'ended_at' => $started->copy()->addMinutes($minutes),
                    'minutes' => $minutes,
                    // Billed against the canned job time, not the clock, which
                    // is why the two columns exist.
                    'billed_hundredths' => (int) round($minutes / 60 * 100 * [1.0, 0.85, 1.2, 0.9, 1.0, 1.1][$i % 6]),
                    'activity' => ['labour', 'diagnosis', 'road_test', 'labour', 'labour', 'diagnosis'][$i % 6],
                ]);
            }

            TimeEntry::create([
                'user_id' => $techs->last()->id,
                'work_order_id' => $jobs->first()->id,
                'started_at' => now()->subDay()->setTime(16, 40),
                'activity' => 'labour',
                'note' => 'Went home without clocking off.',
            ]);
        }

        $this->command?->info('Parts room seeded: '.Part::count().' parts, '
            .Supplier::count().' suppliers, '.PurchaseOrder::count().' purchase orders.');
    }
}
