<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Limits on how a plan can be taken. All optional: null = no limit.
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->unsignedInteger('usage_limit')->nullable()->after('duration_days');      // total sign-ups allowed
            $table->unsignedInteger('per_member_limit')->nullable()->after('usage_limit');   // times one member may take it
            $table->date('available_from')->nullable()->after('per_member_limit');           // on-sale window
            $table->date('available_until')->nullable()->after('available_from');
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();      // stored upper-case
            $table->string('description')->nullable();
            $table->string('discount_type', 10);       // percent | fixed
            $table->decimal('discount_value', 10, 2);
            // null = works on every plan; otherwise only on this plan
            $table->foreignId('membership_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('max_redemptions')->nullable();  // null = unlimited
            $table->boolean('once_per_member')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // One row per time a coupon was used: drives the redemption limits and the audit trail.
        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('discount_amount', 10, 2);
            $table->timestamps();

            $table->index(['coupon_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');

        Schema::table('membership_plans', function (Blueprint $table) {
            $table->dropColumn(['usage_limit', 'per_member_limit', 'available_from', 'available_until']);
        });
    }
};
