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
        // 1. Workflows Root Table
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('cascade');
            $table->decimal('min_amount', 15, 2)->default(0);
            $table->boolean('require_sequential')->default(true); // Defines if Step 1 must complete before 2 starts
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Steps per workflow
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->onDelete('cascade');
            $table->integer('sort_order')->default(1);
            $table->string('name'); // "Manager Review", etc.
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 3. Step Approvers (Pivot User linkage)
        Schema::create('workflow_step_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_step_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        // 4. Audit Log for Approval lifecycle tracking
        Schema::create('order_approval_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('action'); // e.g., 'approved', 'rejected', 'escalated'
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_approval_logs');
        Schema::dropIfExists('workflow_step_users');
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflows');
    }
};
