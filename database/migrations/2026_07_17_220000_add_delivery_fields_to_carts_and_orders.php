<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->string('fulfillment_method', 20)->nullable()->after('currency_code');
            $table->integer('fulfillment_vendor_location_id')->nullable()->after('fulfillment_method');
            $table->uuid('shipping_address_id')->nullable()->after('fulfillment_vendor_location_id');
            $table->json('shipping_address_json')->nullable()->after('shipping_address_id');
            $table->string('shipping_provider', 150)->nullable()->after('shipping_address_json');
            $table->string('shipping_service_code', 100)->nullable()->after('shipping_provider');
            $table->string('shipping_service_name', 150)->nullable()->after('shipping_service_code');
            $table->decimal('shipping_fee', 10, 2)->default(0)->after('shipping_service_name');
            $table->json('shipping_quote_payload')->nullable()->after('shipping_fee');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->integer('fulfillment_vendor_location_id')->nullable()->after('shipping_method');
            $table->uuid('shipping_address_id')->nullable()->after('fulfillment_vendor_location_id');
            $table->json('shipping_address_json')->nullable()->after('shipping_address_id');
            $table->string('shipping_provider', 150)->nullable()->after('shipping_address_json');
            $table->string('shipping_service_code', 100)->nullable()->after('shipping_provider');
            $table->string('shipping_service_name', 150)->nullable()->after('shipping_service_code');
            $table->json('shipping_quote_payload')->nullable()->after('shipping_service_name');
            $table->uuid('delivery_order_id')->nullable()->after('shipping_quote_payload');
            $table->string('delivery_order_no', 100)->nullable()->after('delivery_order_id');
            $table->string('delivery_tracking_no', 100)->nullable()->after('delivery_order_no');
            $table->string('delivery_status', 50)->default('Pending')->after('delivery_tracking_no');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'fulfillment_vendor_location_id',
                'shipping_address_id',
                'shipping_address_json',
                'shipping_provider',
                'shipping_service_code',
                'shipping_service_name',
                'shipping_quote_payload',
                'delivery_order_id',
                'delivery_order_no',
                'delivery_tracking_no',
            ]);
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn([
                'fulfillment_method',
                'fulfillment_vendor_location_id',
                'shipping_address_id',
                'shipping_address_json',
                'shipping_provider',
                'shipping_service_code',
                'shipping_service_name',
                'shipping_fee',
                'shipping_quote_payload',
            ]);
        });
    }
};
