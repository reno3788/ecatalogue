<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Change status from boolean to string enum.
     */
    public function up(): void
    {
        // Convert the boolean status column to varchar using PostgreSQL USING clause
        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE companies ALTER COLUMN status TYPE VARCHAR(20) USING CASE WHEN status THEN 'Active' ELSE 'Inactive' END"
        );
        // Set default
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE companies ALTER COLUMN status SET DEFAULT 'Active'");
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE companies SET status = CASE WHEN status = 'Active' THEN 'true' ELSE 'false' END");
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('status')->default(true)->change();
        });
    }
};
