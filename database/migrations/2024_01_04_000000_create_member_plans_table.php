<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('member_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // member
            $table->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('remaining_credits')->nullable();
            // active | expired | cancelled | pending
            $table->enum('status', ['active', 'expired', 'cancelled', 'pending'])->default('pending');
            $table->boolean('auto_renew')->default(true);
            $table->date('next_billing_date')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_plans');
    }
};
