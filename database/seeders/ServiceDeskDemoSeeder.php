<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Project;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Models\CannedJob;
use App\Models\ServiceReminder;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Database\Seeder;

/**
 * Populates the shop's service desk with believable demo data so the admin panel
 * and the customer portal are not empty on the sandbox. Idempotent: it seeds a
 * customer only if that customer has no tickets yet, so re-running is safe.
 */
class ServiceDeskDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedServices();
        $this->seedVehicles();

        $staff = User::where('role', 'admin')->first() ?? User::first();

        // Attach to whichever demo customers exist. The rich dataset goes to the
        // first customer (so their portal is populated), a lighter one to the
        // second. Prefer the classic persona emails, fall back to any customers.
        $customers = Customer::orderBy('id')->take(2)->get();
        $primary = Customer::where('email', 'ada@example.com')->first() ?? $customers->get(0);
        $secondary = Customer::where('email', 'theo@example.com')->first() ?? $customers->get(1);

        if ($primary && $primary->tickets()->count() === 0) {
            $this->seedPrimary($primary, $staff);
        }

        if ($secondary && $secondary->id !== $primary?->id && $secondary->tickets()->count() === 0) {
            $this->seedSecondary($secondary, $staff);
        }

        // Vehicles arrived after the first release, so a sandbox seeded before
        // then has work already attached to a customer but to no vehicle. Link
        // it up rather than leaving the history page empty on those instances.
        $this->backfillVehicleLinks();
        $this->seedVehicleHistory();
        $this->seedCannedJobs();
        $this->seedReminders();

        // Quotes seed independently (added after the first release) so they
        // populate an already-seeded sandbox too.
        $quoteCustomer = $primary ?? $customers->get(0);
        if ($quoteCustomer && $quoteCustomer->quotes()->count() === 0) {
            $this->seedQuotes($quoteCustomer, $staff);
        }
    }

    /** A sent quote awaiting the customer's decision, plus a draft. */
    private function seedQuotes(Customer $c, ?User $staff): void
    {
        $now = now();

        $sent = Quote::create([
            'customer_id' => $c->id,
            'vehicle_id' => $this->vehicleFor($c)?->id,
            'created_by' => $staff?->id,
            'title' => 'Timing Belt & Water Pump Service',
            'message' => 'Here is the estimate for the timing belt service we discussed at your last visit. Doing the water pump at the same time saves about three hours of labour. This quote is good for 30 days.',
            'status' => 'sent',
            'valid_until' => $now->copy()->addDays(30)->toDateString(),
            'sent_at' => $now->copy()->subDays(2),
        ]);
        QuoteItem::create(['quote_id' => $sent->id, 'name' => 'Timing Belt Kit & Labour', 'quantity' => 1, 'unit_price_cents' => 84000, 'total_cents' => 84000]);
        QuoteItem::create(['quote_id' => $sent->id, 'name' => 'Water Pump & Coolant Refill', 'quantity' => 1, 'unit_price_cents' => 32000, 'total_cents' => 32000]);
        $sent->forceFill(['tax_cents' => 9280])->save();
        $sent->recalcTotals();
        $sent->recordActivity('created', 'Quote created', [], $staff?->id);
        $sent->recordActivity('note', 'Quote Sent To Customer', [], $staff?->id);

        $draft = Quote::create([
            'customer_id' => $c->id,
            'created_by' => $staff?->id,
            'title' => 'Prepaid Oil Change Plan',
            'message' => 'A year of oil changes bought up front, one visit per month at a held price.',
            'status' => 'draft',
        ]);
        QuoteItem::create(['quote_id' => $draft->id, 'name' => 'Prepaid Oil Change Visit', 'quantity' => 12, 'unit_price_cents' => 4900, 'total_cents' => 58800]);
        $draft->recalcTotals();
        $draft->recordActivity('created', 'Quote created', [], $staff?->id);
    }

    /**
     * A vehicle per demo customer, plus a second one for the primary so the
     * "which of your cars" case is visible. Idempotent on VIN.
     */
    private function seedVehicles(): void
    {
        $rows = [
            ['ada@example.com', 2018, 'Chevrolet', 'Silverado 1500', 'LT Crew Cab', '1GCUYDED5KZ182734', 'CFR4821', 'AZ', 'Summit White', '5.3L V8', '8-Speed Automatic', '4WD', 84512],
            ['ada@example.com', 2021, 'Honda', 'CR-V', 'EX-L', '7FARW2H85ME021884', 'BXK9077', 'AZ', 'Modern Steel', '1.5L Turbo I4', 'CVT', 'AWD', 38240],
            ['theo@example.com', 2015, 'Toyota', 'Camry', 'SE', '4T1BF1FK7FU915522', 'TRV2264', 'AZ', 'Blue Streak', '2.5L I4', '6-Speed Automatic', 'FWD', 142870],
            ['priya@example.com', 2019, 'Ford', 'Transit 250', 'Medium Roof', '1FTYR2CM4KKA33417', 'FLT8890', 'AZ', 'Oxford White', '3.7L V6', '6-Speed Automatic', 'RWD', 96430],
        ];

        foreach ($rows as [$email, $year, $make, $model, $trim, $vin, $plate, $state, $color, $engine, $trans, $drive, $miles]) {
            $customer = Customer::where('email', $email)->first();

            Vehicle::firstOrCreate(['vin' => $vin], [
                'customer_id' => $customer?->id,
                'year' => $year,
                'make' => $make,
                'model' => $model,
                'trim' => $trim,
                'plate' => $plate,
                'plate_state' => $state,
                'color' => $color,
                'engine' => $engine,
                'transmission' => $trans,
                'drivetrain' => $drive,
                'mileage' => $miles,
                'mileage_read_on' => now()->subDays(3)->toDateString(),
                'is_active' => true,
            ]);
        }
    }

    /** The jobs a shop quotes over and over, priced with book time. */
    private function seedCannedJobs(): void
    {
        // [name, category, hours, parts cents]
        $rows = [
            ['Oil And Filter Change', 'Maintenance', 0.5, 2400],
            ['Front Brake Pads And Rotors', 'Brakes', 1.8, 24000],
            ['Rear Brake Pads And Rotors', 'Brakes', 1.6, 21000],
            ['Four-Wheel Alignment', 'Tires And Alignment', 1.0, 0],
            ['Coolant System Flush', 'Maintenance', 1.2, 4800],
            ['Serpentine Belt And Tensioner', 'Engine', 1.5, 11000],
            ['AC Recharge And Leak Test', 'Climate', 1.0, 6500],
            ['Battery Replacement', 'Electrical', 0.4, 18500],
        ];

        foreach ($rows as $i => [$name, $category, $hours, $parts]) {
            CannedJob::firstOrCreate(['slug' => CannedJob::uniqueSlugFor($name)], [
                'name' => $name,
                'category' => $category,
                'labour_hundredths' => (int) round($hours * 100),
                'parts_cents' => $parts,
                'is_active' => true,
                'position' => $i,
            ]);
        }
    }

    /** Reminders across the demo vehicles, including one already overdue. */
    private function seedReminders(): void
    {
        if (ServiceReminder::count() > 0) {
            return;
        }

        $oil = CannedJob::where('name', 'Oil And Filter Change')->first();
        $align = CannedJob::where('name', 'Four-Wheel Alignment')->first();

        foreach (Vehicle::with('customer')->orderBy('id')->get() as $i => $vehicle) {
            $current = (int) $vehicle->currentMileage();

            // The first vehicle gets an overdue one, so the list is not all green.
            ServiceReminder::create([
                'vehicle_id' => $vehicle->id,
                'customer_id' => $vehicle->customer_id,
                'canned_job_id' => $oil?->id,
                'name' => 'Oil and filter change due',
                'due_at_miles' => $i === 0 ? max(0, $current - 400) : $current + 2500 + ($i * 900),
                'last_done_miles' => max(0, $current - 4600),
                'last_done_on' => now()->subMonths(5)->toDateString(),
                'status' => 'due',
            ]);

            if ($i % 2 === 0) {
                ServiceReminder::create([
                    'vehicle_id' => $vehicle->id,
                    'customer_id' => $vehicle->customer_id,
                    'canned_job_id' => $align?->id,
                    'name' => 'Annual alignment check',
                    'due_on' => now()->addMonths(2 + $i)->toDateString(),
                    'status' => 'due',
                ]);
            }
        }
    }

    /**
     * A year of past visits on the primary vehicle. Without at least two
     * completed visits a month apart the mileage trend cannot be computed, so a
     * demo would show "Not Enough Data" on the one tile meant to show it off.
     */
    private function seedVehicleHistory(): void
    {
        $vehicle = Vehicle::whereNotNull('customer_id')->orderBy('id')->first();

        if (! $vehicle || $vehicle->workOrders()->whereNotNull('completed_at')->count() >= 3) {
            return;
        }

        $now = now();
        $odo = (int) $vehicle->mileage;

        // [months ago, miles before today's reading, title, line, price cents]
        $past = [
            [11, 13200, 'Oil and filter change, tire rotation', 'Oil & Filter Change', 5900],
            [8, 9400, '30k scheduled service', '30/60/90k Scheduled Service', 34900],
            [5, 5800, 'Four-wheel alignment after new tires', 'Four-Wheel Alignment', 12900],
            [2, 2300, 'Check-engine diagnostic, evap code', 'Check-Engine Diagnostic', 12900],
        ];

        foreach ($past as [$monthsAgo, $milesBack, $title, $line, $cents]) {
            $completedAt = $now->copy()->subMonths($monthsAgo);
            $reading = max(0, $odo - $milesBack);

            $wo = WorkOrder::create([
                'customer_id' => $vehicle->customer_id,
                'vehicle_id' => $vehicle->id,
                'mileage_in' => $reading,
                'mileage_out' => $reading + 4,
                'title' => $title,
                'status' => 'completed',
                'scheduled_at' => $completedAt->copy()->setTime(8, 30),
                'started_at' => $completedAt->copy()->setTime(8, 45),
                'completed_at' => $completedAt->copy()->setTime(10, 15),
                'created_at' => $completedAt->copy()->subDays(2),
            ]);

            WorkOrderItem::create([
                'work_order_id' => $wo->id,
                'name' => $line,
                'quantity' => 1,
                'unit_price_cents' => $cents,
                'total_cents' => $cents,
            ]);

            $wo->recalcTotals();
        }
    }

    /**
     * Attach existing demo work to the customer's vehicle where it has none, and
     * put a plausible odometer on anything already completed so the mileage
     * trend and next-service estimate have something to work from.
     */
    private function backfillVehicleLinks(): void
    {
        foreach (Customer::whereHas('workOrders')->orWhereHas('serviceRequests')->orWhereHas('quotes')->get() as $c) {
            $vehicle = $this->vehicleFor($c);

            if (! $vehicle) {
                continue;
            }

            ServiceRequest::where('customer_id', $c->id)->whereNull('vehicle_id')->update(['vehicle_id' => $vehicle->id]);
            Quote::where('customer_id', $c->id)->whereNull('vehicle_id')->update(['vehicle_id' => $vehicle->id]);

            $orders = WorkOrder::where('customer_id', $c->id)->whereNull('vehicle_id')->orderBy('created_at')->get();

            // Walk backwards from today's odometer so earlier visits read lower,
            // which is what makes the miles-per-day figure believable.
            // The NEWEST visit must land on the vehicle's current reading, not
            // below it, or the vehicle looks like it drove thousands of miles in
            // no time and currentMileage() disagrees with the vehicle record.
            $step = 1200;
            $offset = max(0, $orders->count() - 1) * $step;

            foreach ($orders as $order) {
                $in = max(0, (int) $vehicle->mileage - $offset);
                $order->forceFill([
                    'vehicle_id' => $vehicle->id,
                    'mileage_in' => $in,
                    'mileage_out' => $order->completed_at ? $in + 6 : null,
                ])->save();
                $offset -= $step;
            }
        }
    }

    /** The vehicle a customer's work should be attached to. */
    private function vehicleFor(Customer $c): ?Vehicle
    {
        return Vehicle::where('customer_id', $c->id)->orderBy('id')->first();
    }

    /** A small catalog of billable shop services. */
    private function seedServices(): void
    {
        $services = [
            ['Multi-Point Inspection', 'A 42-point courtesy inspection with photos of anything flagged.', 0],
            ['Wheel Bearing Replacement', 'Press in a new hub bearing assembly and torque to spec.', 38900],
            ['Serpentine Belt & Tensioner', 'Replace the accessory belt and tensioner, check pulley alignment.', 24900],
            ['Coolant System Flush', 'Flush and refill with the manufacturer-spec coolant, pressure test.', 16900],
            ['Prepaid Oil Change Visit', 'One visit drawn from a prepaid oil change plan.', 4900],
        ];

        foreach ($services as [$name, $desc, $priceCents]) {
            $slug = Product::uniqueSlug($name);
            Product::firstOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($name)],
                [
                    'name' => $name,
                    'excerpt' => $desc,
                    'description' => $desc,
                    'status' => 'active',
                    'product_type' => 'service',
                    'requires_shipping' => false,
                ]
            );
        }
    }

    private function seedPrimary(Customer $c, ?User $staff): void
    {
        $now = now();
        $vehicle = $this->vehicleFor($c);

        // A converted request -> ticket -> completed work order -> paid invoice,
        // grouped under a project.
        $project = Project::create([
            'customer_id' => $c->id,
            'assigned_user_id' => $staff?->id,
            'name' => '2018 Silverado 1500 — Brakes & Suspension',
            'description' => 'Staged brake and suspension work on the customer\'s 2018 Silverado 1500, split across two visits so the truck stays drivable.',
            'status' => 'active',
            'starts_on' => $now->copy()->subDays(20)->toDateString(),
            'due_on' => $now->copy()->addDays(20)->toDateString(),
        ]);
        $project->recordActivity('created', 'Project created', [], $staff?->id);

        $req = ServiceRequest::create([
            'customer_id' => $c->id,
            'vehicle_id' => $vehicle?->id,
            'name' => $c->name,
            'email' => $c->email,
            'phone' => '(602) 555-0142',
            'subject' => 'Grinding noise when braking',
            'description' => 'There is a metal-on-metal grinding sound from the front when I brake, and the pedal pulses at highway speed.',
            'status' => 'converted',
            'priority' => 'high',
            'source' => 'web',
        ]);
        $req->recordActivity('created', 'Request submitted', [], null, $c->name);

        $ticket = Ticket::create([
            'customer_id' => $c->id,
            'service_request_id' => $req->id,
            'project_id' => $project->id,
            'assigned_user_id' => $staff?->id,
            'subject' => 'Grinding noise when braking',
            'description' => $req->description,
            'status' => 'resolved',
            'priority' => 'high',
            'last_reply_at' => $now->copy()->subDays(4),
            'last_reply_by' => 'staff',
            'resolved_at' => $now->copy()->subDays(3),
        ]);
        $req->forceFill(['ticket_id' => $ticket->id])->save();
        $ticket->recordActivity('created', 'Ticket opened from request '.$req->number, [], $staff?->id);
        TicketReply::create(['ticket_id' => $ticket->id, 'customer_id' => $c->id, 'author_type' => 'customer', 'author_name' => $c->name, 'body' => 'I need the truck back by the weekend if that is at all possible.', 'is_internal' => false, 'created_at' => $now->copy()->subDays(5)]);
        TicketReply::create(['ticket_id' => $ticket->id, 'user_id' => $staff?->id, 'author_type' => 'staff', 'author_name' => $staff?->name, 'body' => 'Booked you into bay 2 Thursday morning. Front pads and rotors were down to the backing plate, photos are on the inspection.', 'is_internal' => false, 'created_at' => $now->copy()->subDays(4)]);
        $ticket->recordActivity('status', 'Marked resolved', [], $staff?->id);

        $completed = WorkOrder::create([
            'customer_id' => $c->id,
            'vehicle_id' => $vehicle?->id,
            'mileage_in' => $vehicle ? $vehicle->mileage - 1180 : null,
            'mileage_out' => $vehicle ? $vehicle->mileage - 1174 : null,
            'ticket_id' => $ticket->id,
            'project_id' => $project->id,
            'assigned_user_id' => $staff?->id,
            'title' => 'Front brake pads and rotors, road test',
            'status' => 'completed',
            'scheduled_at' => $now->copy()->subDays(3)->setTime(9, 0),
            'started_at' => $now->copy()->subDays(3)->setTime(9, 5),
            'completed_at' => $now->copy()->subDays(3)->setTime(11, 30),
        ]);
        WorkOrderItem::create(['work_order_id' => $completed->id, 'name' => 'Brake Pads & Rotors (Per Axle) — Front', 'quantity' => 1, 'unit_price_cents' => 41900, 'total_cents' => 41900]);
        WorkOrderItem::create(['work_order_id' => $completed->id, 'name' => 'Brake Fluid Flush', 'quantity' => 1, 'unit_price_cents' => 14900, 'total_cents' => 14900]);
        $completed->recalcTotals();
        $completed->recordActivity('completed', 'Work completed', [], $staff?->id);

        // Paid invoice for the completed work order.
        $invoice = Order::create([
            'number' => Order::nextNumber(),
            'customer_id' => $c->id,
            'email' => $c->email,
            'financial_status' => 'paid',
            'status' => 'open',
            'currency' => 'USD',
            'subtotal_cents' => $completed->subtotal_cents,
            'total_cents' => $completed->subtotal_cents,
            'payment_gateway' => 'stripe',
            'payment_provider' => 'stripe',
            'card_brand' => 'visa',
            'card_last4' => '4242',
            'paid_at' => $now->copy()->subDays(3)->setTime(12, 0),
            'work_order_id' => $completed->id,
            'project_id' => $project->id,
        ]);
        foreach ($completed->items as $item) {
            OrderItem::create(['order_id' => $invoice->id, 'name' => $item->name, 'quantity' => $item->quantity, 'unit_price_cents' => $item->unit_price_cents, 'total_cents' => $item->total_cents, 'requires_shipping' => false]);
        }
        $completed->forceFill(['invoice_order_id' => $invoice->id])->save();

        // An upcoming scheduled work order with an unpaid invoice due after it.
        $upcoming = WorkOrder::create([
            'customer_id' => $c->id,
            'vehicle_id' => $vehicle?->id,
            'project_id' => $project->id,
            'assigned_user_id' => $staff?->id,
            'title' => 'Rear pads and four-wheel alignment',
            'status' => 'scheduled',
            'scheduled_at' => $now->copy()->addDays(4)->setTime(10, 0),
        ]);
        WorkOrderItem::create(['work_order_id' => $upcoming->id, 'name' => 'Four-Wheel Alignment', 'quantity' => 1, 'unit_price_cents' => 12900, 'total_cents' => 12900]);
        $upcoming->recalcTotals();
        $upcoming->recordActivity('scheduled', 'Scheduled for '.$upcoming->scheduled_at->format('M j, g:i A'), [], $staff?->id);

        // A separate open ticket, not yet converted.
        $open = Ticket::create([
            'customer_id' => $c->id,
            'assigned_user_id' => $staff?->id,
            'subject' => 'Check-engine light came back on',
            'description' => 'The light cleared for about a week after the last visit and has come back on.',
            'status' => 'in_progress',
            'priority' => 'normal',
            'last_reply_at' => $now->copy()->subDay(),
            'last_reply_by' => 'customer',
        ]);
        $open->recordActivity('created', 'Ticket opened', [], null, $c->name);
        TicketReply::create(['ticket_id' => $open->id, 'customer_id' => $c->id, 'author_type' => 'customer', 'author_name' => $c->name, 'body' => 'Same light, no difference in how it drives. Can someone pull the code again?', 'is_internal' => false, 'created_at' => $now->copy()->subDay()]);
    }

    private function seedSecondary(Customer $c, ?User $staff): void
    {
        $now = now();
        $vehicle = $this->vehicleFor($c);

        $req = ServiceRequest::create([
            'customer_id' => $c->id,
            'vehicle_id' => $vehicle?->id,
            'name' => $c->name,
            'email' => $c->email,
            'subject' => 'AC blowing warm air',
            'description' => 'The AC blows warm once the car has been sitting in the sun, even on the coldest setting.',
            'status' => 'new',
            'priority' => 'urgent',
            'source' => 'web',
        ]);
        $req->recordActivity('created', 'Request submitted', [], null, $c->name);

        $wo = WorkOrder::create([
            'customer_id' => $c->id,
            'vehicle_id' => $vehicle?->id,
            'mileage_in' => $vehicle?->mileage,
            'assigned_user_id' => $staff?->id,
            'title' => 'AC leak test and recharge',
            'status' => 'in_progress',
            'scheduled_at' => $now->copy()->setTime(14, 0),
            'started_at' => $now->copy()->setTime(14, 10),
        ]);
        WorkOrderItem::create(['work_order_id' => $wo->id, 'name' => 'AC Recharge & Leak Test', 'quantity' => 1, 'unit_price_cents' => 18900, 'total_cents' => 18900]);
        $wo->recalcTotals();
        $wo->recordActivity('scheduled', 'Vehicle in bay 3', [], $staff?->id);
    }
}
