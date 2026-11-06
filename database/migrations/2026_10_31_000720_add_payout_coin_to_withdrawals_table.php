<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Withdrawals can now be paid in BTC, ETH or SOL as well as USDT. The coin
     * amount and the price used are fixed when the user asks, so the admin
     * knows exactly what to send. Solana signatures need a longer payout ID.
     */
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('coin', 12)->default('USDT')->after('network');
            $table->string('coin_amount', 40)->nullable()->after('coin');
            $table->unsignedBigInteger('price_cents')->nullable()->after('coin_amount');
            $table->string('payout_txid', 120)->nullable()->change();
        });

        DB::table('withdrawals')->where('network', 'bitcoin')->update(['coin' => 'BTC']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn(['coin', 'coin_amount', 'price_cents']);
        });
    }
};
