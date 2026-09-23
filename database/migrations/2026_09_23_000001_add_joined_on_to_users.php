<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * A member's join date ("Member since"), kept apart from created_at so the owner can correct it — e.g. for
     * members who joined before the software was in use, or who were entered with a back-dated first plan.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('joined_on')->nullable()->after('is_active');
        });

        // Existing members: the day their account was made, or their first plan's start if that was earlier.
        DB::table('users')->where('role', 'member')->update(['joined_on' => DB::raw('DATE(created_at)')]);
        DB::table('users')->where('role', 'member')->update(['joined_on' => DB::raw(
            'COALESCE((SELECT MIN(mp.start_date) FROM member_plans mp WHERE mp.user_id = users.id AND mp.start_date < users.joined_on), users.joined_on)'
        )]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('joined_on');
        });
    }
};
