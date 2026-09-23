<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Money going out: rent, bills, repairs, supplies, petty cash at the desk… (salaries are paid through Payroll).
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('spent_on');
            $table->string('category', 30);                          // see Expense::CATEGORIES
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->string('method', 20)->default('cash');           // cash | upi | card | bank_transfer
            $table->string('paid_to')->nullable();                   // vendor / person
            $table->string('reference', 100)->nullable();            // bill or transaction number
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('spent_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
