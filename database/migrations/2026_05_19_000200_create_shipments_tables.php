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
        // Create shipments table
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('carrier_id')->nullable()->constrained('carriers')->onDelete('set null');
            $table->string('tracking_number');
            $table->text('notes')->nullable();
            $table->string('delivery_note_path')->nullable();
            $table->timestamps();
        });

        // Create shipment_items table
        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->onDelete('cascade');
            $table->foreignId('order_item_id')->constrained('order_items')->onDelete('cascade');
            $table->integer('quantity');
            $table->timestamps();
        });

        // Add invoice columns to orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->string('invoice_attachment')->nullable()->after('po_attachment');
            $table->json('invoice_documents')->nullable()->after('invoice_attachment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['invoice_attachment', 'invoice_documents']);
        });

        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
    }
};
