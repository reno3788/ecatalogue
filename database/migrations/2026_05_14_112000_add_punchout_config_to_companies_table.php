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
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('punchout_enabled')->default(false);
            $table->string('punchout_gateway')->default('abeta');
            $table->text('punchout_url')->nullable();
            $table->string('punchout_identity')->nullable();
            $table->string('punchout_shared_secret')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'punchout_enabled',
                'punchout_gateway',
                'punchout_url',
                'punchout_identity',
                'punchout_shared_secret'
            ]);
        });
    }
};
