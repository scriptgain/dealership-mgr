<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The digital vehicle inspection. This is the feature that sells shop software:
 * a technician records what they found with photos, the customer opens a link on
 * their phone, sees the worn brake pad next to the price, and approves or
 * declines each line individually. Only approved lines become billable work.
 *
 * Two things make it work rather than being a glorified checklist:
 *
 *   PER LINE DECISIONS  the customer says yes to brakes and no to wipers, and
 *                       both answers are recorded against the line with a
 *                       timestamp. "I never approved that" stops being a
 *                       conversation at pickup.
 *
 *   SEVERITY           red / yellow / green, so a customer scanning on a phone
 *                      sees what is unsafe versus what can wait, and the shop is
 *                      not accused of upselling everything at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();

            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            // The visit this inspection was performed on. Nullable: a shop can
            // inspect a vehicle before any repair order exists.
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('mileage')->nullable();
            $table->text('summary')->nullable();

            // Unguessable token rather than an incrementing id, so a customer
            // link cannot be walked to read someone else's inspection.
            $table->string('review_token', 64)->unique()->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'status']);
        });

        Schema::create('inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();

            $table->string('category')->nullable();
            $table->string('name');
            $table->text('finding')->nullable();
            // What the technician measured, kept as free text because units vary:
            // "3mm", "42 psi", "P0442", "1/4 turn of play".
            $table->string('measurement', 64)->nullable();

            $table->string('severity')->default('ok')->index();

            // The recommended fix, priced. Links to the service catalogue when it
            // is a standard job, or stands alone for one-off work.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('price_cents')->nullable();

            // The customer's answer to THIS line, not to the inspection.
            $table->string('decision')->default('pending')->index();
            $table->timestamp('decided_at')->nullable();

            // Set once the approved line has been pushed onto the repair order,
            // so pressing the button twice cannot bill the customer twice.
            $table->foreignId('work_order_item_id')->nullable()->constrained('work_order_items')->nullOnDelete();

            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['inspection_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_items');
        Schema::dropIfExists('inspections');
    }
};
