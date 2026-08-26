<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_shipping_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('contact_name');
            $table->string('contact_phone', 32);
            $table->string('contact_email')->nullable();
            $table->text('address_line');
            $table->string('district')->nullable();
            $table->string('city');
            $table->string('province');
            $table->string('postal_code', 12);
            $table->string('area_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('note')->nullable();
            $table->string('collection_method')->default('pickup');
            $table->json('enabled_couriers')->nullable();
            $table->boolean('default_insurance')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('weight_gram')->default(0)->after('sku');
            $table->unsignedSmallInteger('length_cm')->default(0)->after('weight_gram');
            $table->unsignedSmallInteger('width_cm')->default(0)->after('length_cm');
            $table->unsignedSmallInteger('height_cm')->default(0)->after('width_cm');
            $table->string('shipping_category')->default('others')->after('height_cm');
            $table->boolean('is_fragile')->default(false)->after('shipping_category');
        });

        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->string('area_id')->nullable()->after('postal_code');
            $table->decimal('latitude', 10, 7)->nullable()->after('area_id');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->text('note')->nullable()->after('longitude');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_provider')->nullable()->after('shipping_method');
            $table->string('shipping_service')->nullable()->after('shipping_provider');
            $table->string('shipping_courier')->nullable()->after('shipping_service');
            $table->string('shipping_courier_type')->nullable()->after('shipping_courier');
            $table->decimal('shipping_insurance', 14, 2)->default(0)->after('shipping_total');
            $table->json('shipping_quote')->nullable()->after('shipping_insurance');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            $table->timestamp('auto_complete_at')->nullable()->after('delivered_at');
            $table->timestamp('complaint_deadline_at')->nullable()->after('auto_complete_at');
            $table->timestamp('funds_release_at')->nullable()->after('complaint_deadline_at');
            $table->timestamp('buyer_confirmed_at')->nullable()->after('funds_release_at');

            $table->index(['fulfillment_status', 'auto_complete_at']);
            $table->index(['status', 'funds_release_at']);
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider')->default('manual');
            $table->string('external_id')->nullable()->unique();
            $table->string('reference')->nullable();
            $table->string('courier_company')->nullable();
            $table->string('courier_type')->nullable();
            $table->string('courier_name')->nullable();
            $table->string('waybill_id')->nullable();
            $table->string('tracking_id')->nullable();
            $table->text('tracking_url')->nullable();
            $table->string('status')->default('pending');
            $table->decimal('quoted_price', 14, 2)->default(0);
            $table->decimal('actual_price', 14, 2)->nullable();
            $table->decimal('insurance_fee', 14, 2)->default(0);
            $table->string('collection_method')->default('pickup');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('provider_response')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status']);
            $table->index('waybill_id');
        });

        Schema::create('shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('external_event_id')->nullable();
            $table->string('status');
            $table->string('description')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('event_at');
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['shipment_id', 'external_event_id']);
            $table->index(['shipment_id', 'event_at']);
        });

        Schema::create('order_disputes', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('OPEN');
            $table->string('resolution')->nullable();
            $table->text('description');
            $table->json('evidence')->nullable();
            $table->text('seller_response')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('seller_response_due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_disputes');
        Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('shipments');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['fulfillment_status', 'auto_complete_at']);
            $table->dropIndex(['status', 'funds_release_at']);
            $table->dropColumn([
                'shipping_provider', 'shipping_service', 'shipping_courier', 'shipping_courier_type',
                'shipping_insurance', 'shipping_quote', 'delivered_at', 'auto_complete_at',
                'complaint_deadline_at', 'funds_release_at', 'buyer_confirmed_at',
            ]);
        });

        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropColumn(['area_id', 'latitude', 'longitude', 'note']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'weight_gram', 'length_cm', 'width_cm', 'height_cm', 'shipping_category', 'is_fragile',
            ]);
        });

        Schema::dropIfExists('store_shipping_profiles');
    }
};
