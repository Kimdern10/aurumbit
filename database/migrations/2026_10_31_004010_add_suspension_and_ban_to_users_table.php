<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suspensions can now end on a date and carry a reason; bans are permanent.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_until')->nullable()->after('is_suspended');
            $table->string('suspension_reason', 500)->nullable()->after('suspended_until');
            $table->timestamp('banned_at')->nullable()->after('suspension_reason');
            $table->string('ban_reason', 500)->nullable()->after('banned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['suspended_until', 'suspension_reason', 'banned_at', 'ban_reason']);
        });
    }
};
