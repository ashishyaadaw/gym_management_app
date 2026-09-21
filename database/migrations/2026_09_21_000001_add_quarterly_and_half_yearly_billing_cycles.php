<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Plans can now be billed monthly, quarterly, half-yearly, yearly or per class.
     * Until now a 3- or 6-month plan had to be saved as "monthly" with a longer duration, so those are relabelled.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE membership_plans MODIFY billing_cycle ENUM('monthly','quarterly','half_yearly','yearly','pay_per_class') NOT NULL");
        }

        DB::table('membership_plans')->where('billing_cycle', 'monthly')->where('duration_days', 90)->update(['billing_cycle' => 'quarterly']);
        DB::table('membership_plans')->where('billing_cycle', 'monthly')->where('duration_days', 180)->update(['billing_cycle' => 'half_yearly']);
    }

    public function down(): void
    {
        DB::table('membership_plans')->whereIn('billing_cycle', ['quarterly', 'half_yearly'])->update(['billing_cycle' => 'monthly']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE membership_plans MODIFY billing_cycle ENUM('monthly','yearly','pay_per_class') NOT NULL");
        }
    }
};
