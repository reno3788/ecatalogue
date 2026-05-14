<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add extended tracking and routing metadata to core orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->string('deployment_mode')->nullable()->after('status');
            $table->string('po_date')->nullable()->after('punchout_po_reference');
            $table->string('currency', 3)->nullable()->after('total');
            
            // Shipping metadata
            $table->string('shipping_name')->nullable();
            $table->string('shipping_email')->nullable();
            $table->text('shipping_address')->nullable();

            // Billing metadata
            $table->string('billing_name')->nullable();
            $table->string('billing_email')->nullable();
            $table->text('billing_address')->nullable();
        });

        // Add B2B structural tracking and supplier metadata to line items
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('uom')->nullable()->after('price');
            $table->string('classification')->nullable()->after('uom');
            $table->string('manufacturer_part_id')->nullable()->after('classification');
            $table->string('manufacturer_name')->nullable()->after('manufacturer_part_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'deployment_mode',
                'po_date',
                'currency',
                'shipping_name',
                'shipping_email',
                'shipping_address',
                'billing_name',
                'billing_email',
                'billing_address',
            ]);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'uom',
                'classification',
                'manufacturer_part_id',
                'manufacturer_name',
            ]);
        });
    }
};
