<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Employment details for anyone who works at the gym (admin / trainer / receptionist accounts).
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('employee_code', 20)->unique();
            $table->string('designation')->nullable();
            $table->date('joining_date');
            $table->date('leaving_date')->nullable();
            $table->string('pay_type', 10)->default('monthly');   // monthly | hourly
            $table->decimal('base_salary', 10, 2)->default(0);    // per month, or per hour when pay_type = hourly
            $table->time('shift_start')->nullable();
            $table->time('shift_end')->nullable();
            $table->unsignedTinyInteger('weekly_off')->default(0); // 0 = Sunday … 6 = Saturday
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        // One row per staff member per day they have been marked (or clocked in). No row = not marked.
        Schema::create('staff_attendances', function (Blueprint $table) {
            $table->id();
            // restrict: history must not vanish because an account was deleted
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('work_date');
            $table->string('status', 12);                          // present | half_day | absent | leave | holiday
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->boolean('is_late')->default(false);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->string('note')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
            $table->index('work_date');
        });

        // A month's salary for one person. The figures are a snapshot, so later changes to pay or
        // attendance never rewrite a payslip that has already been issued.
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('month');                                  // first day of the month
            $table->string('pay_type', 10);
            $table->decimal('base_salary', 10, 2);
            $table->unsignedTinyInteger('days_in_month');
            $table->unsignedTinyInteger('present_days')->default(0);
            $table->unsignedTinyInteger('half_days')->default(0);
            $table->unsignedTinyInteger('leave_days')->default(0);
            $table->unsignedTinyInteger('holiday_days')->default(0);
            $table->unsignedTinyInteger('weekly_off_days')->default(0);
            $table->unsignedTinyInteger('unmarked_days')->default(0);
            $table->decimal('absent_days', 4, 1)->default(0);       // full absences + half of each half day
            $table->unsignedTinyInteger('late_marks')->default(0);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->decimal('payable_days', 4, 1)->default(0);
            $table->decimal('gross_pay', 10, 2)->default(0);
            $table->decimal('late_deduction', 10, 2)->default(0);
            $table->decimal('bonus', 10, 2)->default(0);
            $table->decimal('other_deduction', 10, 2)->default(0);
            $table->string('adjustment_note')->nullable();
            $table->decimal('net_pay', 10, 2)->default(0);
            $table->string('status', 10)->default('draft');         // draft | paid
            $table->date('paid_on')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->string('payment_reference')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'month']);
            $table->index('month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('staff_attendances');
        Schema::dropIfExists('staff_profiles');
    }
};
