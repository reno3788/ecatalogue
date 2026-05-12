<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('base_price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('client_price_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->decimal('custom_price', 10, 2);
            $table->timestamps();
            
            $table->unique(['company_id', 'product_id']);
        });

        Schema::create('catalog_visibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_visibility');
        Schema::dropIfExists('client_price_lists');
        Schema::dropIfExists('products');
    }
};
