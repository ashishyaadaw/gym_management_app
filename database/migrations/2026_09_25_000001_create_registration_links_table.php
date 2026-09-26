<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // One-time sign-up links the front desk shares with a new member, who fills in their own details
        // (no plan is started — the desk adds one later with "Renew").
        Schema::create('registration_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('label')->nullable();                    // who it was sent to, e.g. "Rahul (9876543210)"
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('member_id')->nullable()->constrained('users')->nullOnDelete(); // the member it registered
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_links');
    }
};
