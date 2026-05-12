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
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('PT. Metro Pacific Tbk');
            $table->string('logo_path')->nullable();
            $table->string('currency')->default('EUR');
            $table->timestamps();
        });

        // Seed default application setting
        \Illuminate\Support\Facades\DB::table('app_settings')->insert([
            'name' => 'PT. Metro Pacific Tbk',
            'logo_path' => null,
            'currency' => 'EUR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
