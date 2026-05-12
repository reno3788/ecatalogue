<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('order_approval_logs', 'workflow_step_id')) {
            Schema::table('order_approval_logs', function (Blueprint $table) {
                $table->foreignId('workflow_step_id')->after('user_id')->nullable()->constrained()->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::table('order_approval_logs', function (Blueprint $table) {
            $table->dropForeign(['workflow_step_id']);
            $table->dropColumn('workflow_step_id');
        });
    }
};
