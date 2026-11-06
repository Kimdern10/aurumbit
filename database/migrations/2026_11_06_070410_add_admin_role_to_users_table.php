<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What each admin may do. Admins that already exist keep full access.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('admin_role')->nullable()->after('is_admin');
        });

        DB::table('users')->where('is_admin', true)->update(['admin_role' => 'owner']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('admin_role');
        });
    }
};
