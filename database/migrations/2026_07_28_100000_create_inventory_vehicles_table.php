<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sales inventory: the vehicles the dealership is selling.
 *
 * Deliberately separate from the inherited `vehicles` table, which holds a
 * CUSTOMER's own car for service work and hangs off customer_id. A unit on the
 * lot has no owner yet, carries a stock number, a price and a sale status, and
 * outlives any one customer relationship.
 *
 * Money is stored as an integer in the currency's minor unit, matching the rest
 * of the app (config('dealership.currency_decimals') drives display).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('stock_number')->unique();
            $table->string('vin', 32)->nullable()->unique();
            $table->string('slug')->unique();

            $table->unsignedSmallInteger('year');
            $table->string('make');
            $table->string('model');
            $table->string('trim')->nullable();
            $table->string('body_type')->nullable();

            // new | used | certified
            $table->string('condition', 16)->default('used');
            // available | pending | sold
            $table->string('status', 16)->default('available');

            $table->unsignedInteger('mileage')->default(0);
            $table->unsignedInteger('price')->nullable();
            $table->unsignedInteger('msrp')->nullable();
            $table->unsignedInteger('cost')->nullable();

            $table->string('exterior_color')->nullable();
            $table->string('interior_color')->nullable();
            $table->string('transmission')->nullable();
            $table->string('drivetrain', 16)->nullable();
            $table->string('fuel_type')->nullable();
            $table->string('engine')->nullable();
            $table->unsignedTinyInteger('doors')->nullable();
            $table->unsignedTinyInteger('seats')->nullable();
            $table->unsignedSmallInteger('mpg_city')->nullable();
            $table->unsignedSmallInteger('mpg_highway')->nullable();

            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->json('photos')->nullable();

            $table->boolean('is_featured')->default(false);
            $table->date('listed_on')->nullable();
            $table->date('sold_on')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'is_featured']);
            $table->index(['make', 'model']);
            $table->index('price');
            $table->index('year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_vehicles');
    }
};
