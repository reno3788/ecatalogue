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
        Schema::table('products', function (Blueprint $table) {
            $table->string('image')->nullable()->after('description');
            $table->decimal('weight', 8, 2)->nullable()->after('image');
            $table->string('color')->nullable()->after('weight');
            $table->string('size')->nullable()->after('color');
            $table->string('brand')->nullable()->after('size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['image', 'weight', 'color', 'size', 'brand']);
        });
    }
};
