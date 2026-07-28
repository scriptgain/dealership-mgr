<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two things a shop needs that a generic service desk does not have.
 *
 * CANNED JOBS  the work a shop does over and over, priced once. A brake job is
 *              always pads, rotors, fluid and 1.8 hours of labour, so quoting it
 *              should be one click rather than four typed lines and a mistake.
 *
 * REMINDERS    the difference between a customer who comes back and one who
 *              drifts. Due either by date or by odometer, and the odometer case
 *              is why the mileage trend on a vehicle matters: it turns "due at
 *              90,000" into "due in about three weeks".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canned_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->nullable();
            $table->text('description')->nullable();

            // Book time. Stored in hundredths of an hour so 1.8 is exact rather
            // than a float that drifts, matching how money is handled here.
            $table->unsignedInteger('labour_hundredths')->default(0);
            // Optional override of the shop's default rate, for warranty or
            // fleet work billed at a different rate.
            $table->unsignedInteger('labour_rate_cents')->nullable();
            $table->unsignedInteger('parts_cents')->default(0);

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'category']);
        });

        Schema::create('service_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('canned_job_id')->nullable()->constrained()->nullOnDelete();
            // The visit that satisfied this reminder, once one does.
            $table->foreignId('completed_work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();

            $table->string('name');
            $table->text('notes')->nullable();

            // Either or both. A due date alone suits an annual inspection; an
            // odometer target alone suits an oil change; both suits most things.
            $table->date('due_on')->nullable();
            $table->unsignedInteger('due_at_miles')->nullable();

            // What the odometer read when the work was last done, so the next
            // interval can be computed from the right baseline.
            $table->unsignedInteger('last_done_miles')->nullable();
            $table->date('last_done_on')->nullable();

            $table->string('status')->default('due')->index();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'status']);
            $table->index(['status', 'due_on']);
        });

        // A repair-order line can say which canned job produced it, so a shop can
        // see what its standard jobs actually earn.
        Schema::table('work_order_items', function (Blueprint $table) {
            $table->foreignId('canned_job_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('canned_job_id');
        });

        Schema::dropIfExists('service_reminders');
        Schema::dropIfExists('canned_jobs');
    }
};
