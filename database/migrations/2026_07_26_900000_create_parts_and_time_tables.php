<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parts, suppliers, and the clock.
 *
 * These three are what separate a repair shop from a general service business,
 * and each carries a rule that a spreadsheet cannot hold:
 *
 *   PARTS     stock is a ledger, not a number. Every movement is a row, and
 *             the on-hand figure is the sum of them. A shop whose count is a
 *             single mutable integer can never answer "where did the other two
 *             go", which is the only question anyone ever asks about parts.
 *
 *   SUPPLIERS the same part comes from three factors at three prices with three
 *             lead times, and which one you call depends on whether the car is
 *             on a lift right now. So the part-supplier link is its own record.
 *
 *   THE CLOCK a technician is on exactly one job at a time. Not zero and not
 *             two. Time that lands on two repair orders at once is how a shop
 *             bills more labour hours than the day contains.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('account_number')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->string('terms')->nullable();
            // Typical, not guaranteed. Used to warn when a job is promised
            // sooner than the part can arrive.
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->string('part_number');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->nullable();
            $table->string('brand')->nullable();
            $table->text('description')->nullable();

            $table->unsignedBigInteger('cost_cents')->default(0);
            $table->unsignedBigInteger('price_cents')->default(0);

            // A core charge is refunded when the old unit comes back, so it is
            // not part of the price and must not be marked up.
            $table->unsignedBigInteger('core_charge_cents')->nullable();

            $table->string('bin_location')->nullable();
            $table->unsignedInteger('reorder_point')->nullable();
            $table->unsignedInteger('reorder_qty')->nullable();

            // Supersession. A part number that has been replaced still appears
            // in old repair orders and must still be findable.
            $table->foreignId('superseded_by_part_id')->nullable()->constrained('parts')->nullOnDelete();

            $table->boolean('is_stocked')->default(true);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['part_number', 'brand']);
            $table->index(['is_active', 'category']);
        });

        Schema::create('part_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_part_number')->nullable();
            $table->unsignedBigInteger('cost_cents')->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->timestamp('last_quoted_at')->nullable();
            $table->timestamps();

            $table->unique(['part_id', 'supplier_id']);
        });

        // The ledger. on_hand is derived from this, never set directly.
        Schema::create('part_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            // received|issued|returned|adjusted|scrapped
            $table->string('kind')->index();
            // Signed: positive puts stock on, negative takes it off.
            $table->integer('qty');
            $table->unsignedBigInteger('unit_cost_cents')->nullable();

            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['part_id', 'created_at']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ordered_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status')->default('draft')->index(); // draft|ordered|partial|received|cancelled
            $table->date('expected_on')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('supplier_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('qty');
            $table->unsignedInteger('qty_received')->default(0);
            $table->unsignedBigInteger('unit_cost_cents')->default(0);
            $table->timestamps();

            $table->unique(['purchase_order_id', 'part_id']);
        });

        // The clock. One open entry per technician, enforced in the model and
        // by a partial expectation here: closed entries have an ended_at.
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_order_item_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            // Cached on close so reports do not recompute across a million rows.
            $table->unsignedInteger('minutes')->nullable();

            // Clock time is what happened; billed time is what the customer
            // pays, and on a canned job those are deliberately different.
            $table->unsignedInteger('billed_hundredths')->nullable();

            $table->string('activity')->default('labour'); // labour|diagnosis|road_test|admin
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
            $table->index(['work_order_id', 'started_at']);
        });

        // A line on a repair order can now be a part rather than a service.
        if (Schema::hasTable('work_order_items') && ! Schema::hasColumn('work_order_items', 'part_id')) {
            Schema::table('work_order_items', function (Blueprint $table) {
                $table->foreignId('part_id')->nullable()->after('service_id')->constrained()->nullOnDelete();
                $table->string('line_type')->default('service')->after('part_id'); // service|part|labour|sublet|fee
                $table->unsignedBigInteger('unit_cost_cents')->nullable()->after('unit_price_cents');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('work_order_items') && Schema::hasColumn('work_order_items', 'part_id')) {
            Schema::table('work_order_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('part_id');
                $table->dropColumn(['line_type', 'unit_cost_cents']);
            });
        }

        foreach ([
            'time_entries', 'purchase_order_items', 'purchase_orders',
            'part_movements', 'part_supplier', 'parts', 'suppliers',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
