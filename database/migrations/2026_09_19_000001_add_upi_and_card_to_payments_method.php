<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /** Front-desk collections are split Cash / UPI / Card, so payments need "upi" and "card" methods. */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY method ENUM('credit_card','debit_card','cash','bank_transfer','upi','card','other') NOT NULL DEFAULT 'cash'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('payments')->whereIn('method', ['upi', 'card'])->update(['method' => 'other']);
            DB::statement("ALTER TABLE payments MODIFY method ENUM('credit_card','debit_card','cash','bank_transfer','other') NOT NULL DEFAULT 'cash'");
        }
    }
};
