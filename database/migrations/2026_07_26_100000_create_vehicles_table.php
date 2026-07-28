<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The vehicle is the object a repair shop actually works on. Everything a shop
 * looks up starts here: "what did we do to this truck last time", "is that
 * noise the same one we quoted in March", "when is this fleet van due again".
 *
 * A vehicle belongs to a customer, and work orders and service requests point
 * at it, so the service history is just the work orders for that vehicle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedSmallInteger('year')->nullable();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('trim')->nullable();

            // A VIN is 17 characters on anything built since 1981, but older and
            // imported vehicles have shorter ones, so this is not length-locked.
            // Unique where present: two customers cannot own the same VIN.
            $table->string('vin')->nullable()->unique();
            $table->string('plate')->nullable();
            $table->string('plate_state', 8)->nullable();

            $table->string('color')->nullable();
            $table->string('engine')->nullable();
            $table->string('transmission')->nullable();
            $table->string('drivetrain', 16)->nullable();

            // Odometer, tracked with the date it was read so a service-interval
            // reminder can estimate miles/day rather than guess.
            $table->unsignedInteger('mileage')->nullable();
            $table->date('mileage_read_on')->nullable();

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['customer_id', 'is_active']);
            $table->index('plate');
            $table->index(['make', 'model']);
        });

        // The job in the bay and the request that started it both point at a
        // vehicle. Nullable: a shop can still log work without identifying one.
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            // Odometer at the time of service — the number that drives the next
            // interval, recorded per visit rather than only on the vehicle.
            $table->unsignedInteger('mileage_in')->nullable()->after('vehicle_id');
            $table->unsignedInteger('mileage_out')->nullable()->after('mileage_in');
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['quotes', 'service_requests'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropConstrainedForeignId('vehicle_id');
            });
        }

        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_id');
            $table->dropColumn(['mileage_in', 'mileage_out']);
        });

        Schema::dropIfExists('vehicles');
    }
};
