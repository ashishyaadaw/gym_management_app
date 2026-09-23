<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Past records: the owner can enter the gym's history (payments from before the software was installed).
     *  - source = 'history' marks those entries, so they can be listed and undone.
     *  - recorded_by = who typed the entry in.
     *  - user_id becomes optional for lump sums from an old daybook ("₹12,000 collected on 5 Mar") with no member.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('source', 20)->nullable()->after('notes');
            $table->foreignId('recorded_by')->nullable()->after('source')->constrained('users')->nullOnDelete();

            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recorded_by');
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
        // user_id is left nullable: lump-sum rows would violate NOT NULL, and nothing relies on it being required.
    }
};
