<?php

use App\Services\MarketPrices;
use App\Support\Btc;
use App\Support\Usdt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Balances move from BTC (satoshis) to USDT (micros, 1 USDT = 1,000,000).
 *
 * Every stored amount is converted once at the live BTC price, and each user
 * with a balance gets a "Converted to USDT" line in their history. Deposits
 * now record the coin and amount the user sent; the USDT credited is set by the
 * admin on approval. The old single BTC deposit address becomes a deposit wallet.
 *
 * Set BTC_USD_CONVERSION_PRICE (e.g. 76728.00) to convert at a fixed price
 * instead of the live one.
 */
return new class extends Migration
{
    /**
     * Columns holding satoshis, renamed to the same name with _micros.
     *
     * @var array<string, list<string>>
     */
    private const array COLUMNS = [
        'users' => ['balance_sats'],
        'plans' => ['min_sats', 'max_sats'],
        'investments' => ['amount_sats', 'earned_sats'],
        'ledger_entries' => ['amount_sats', 'balance_after_sats'],
        'withdrawals' => ['amount_sats'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $priceCents = $this->btcPriceCents();

        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->renameColumn($column, str_replace('_sats', '_micros', $column));
                }
            });

            if ($priceCents === null) {
                continue;
            }

            DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table, $columns, $priceCents) {
                foreach ($rows as $row) {
                    $values = [];

                    foreach ($columns as $column) {
                        $micros = str_replace('_sats', '_micros', $column);

                        if ($row->{$micros} !== null) {
                            $values[$micros] = Usdt::fromSats((int) $row->{$micros}, $priceCents);
                        }
                    }

                    if ($table === 'plans') {
                        $values = array_map(fn (int $value): int => max(Usdt::MICROS_PER_USDT, (int) round($value / Usdt::MICROS_PER_USDT) * Usdt::MICROS_PER_USDT), $values);
                    }

                    DB::table($table)->where('id', $row->id)->update($values);
                }
            });
        }

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->renameColumn('btc_address', 'address');
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('network', 10)->default('trc20')->after('amount_micros');
            $table->string('payout_txid', 80)->nullable()->change();
        });

        DB::table('withdrawals')->update(['network' => 'bitcoin']);

        $this->convertDeposits($priceCents);
        $this->recordConversions($priceCents);
        $this->convertSettings($priceCents);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Balances were converted from BTC to USDT and cannot be converted back. Restore a database backup instead.');
    }

    /**
     * The BTC price to convert at, or null when there is nothing to convert.
     */
    private function btcPriceCents(): ?int
    {
        $hasMoney = DB::table('users')->where('balance_sats', '>', 0)->exists()
            || DB::table('ledger_entries')->exists()
            || DB::table('deposits')->exists()
            || DB::table('plans')->exists();

        if (! $hasMoney) {
            return null;
        }

        $fixed = config('services.market_prices.btc_usd_conversion_price');

        if (is_numeric($fixed) && (float) $fixed > 0) {
            return (int) round((float) $fixed * 100);
        }

        Cache::forget('market-prices');
        $priceCents = app(MarketPrices::class)->current()['btc_cents'];

        if ($priceCents === null) {
            throw new RuntimeException('Could not get the live BTC price to convert balances to USDT. Try again, or set BTC_USD_CONVERSION_PRICE in .env.');
        }

        return $priceCents;
    }

    private function convertDeposits(?int $priceCents): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->foreignId('deposit_wallet_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('coin', 12)->default('BTC')->after('deposit_wallet_id');
            $table->string('network', 40)->nullable()->after('coin');
            $table->string('coin_amount', 40)->default('0')->after('network');
            $table->unsignedBigInteger('credited_micros')->nullable()->after('coin_amount');
        });

        DB::table('deposits')->orderBy('id')->chunkById(500, function ($rows) use ($priceCents) {
            foreach ($rows as $row) {
                DB::table('deposits')->where('id', $row->id)->update([
                    'coin' => 'BTC',
                    'network' => 'Bitcoin',
                    'coin_amount' => str_replace(',', '', Btc::format((int) $row->amount_sats)),
                    'credited_micros' => $row->status === 'approved' && $priceCents !== null ? Usdt::fromSats((int) $row->amount_sats, $priceCents) : null,
                ]);
            }
        });

        Schema::table('deposits', function (Blueprint $table) {
            $table->dropColumn('amount_sats');
        });
    }

    /**
     * Add a zero-amount history line so each user can see the conversion and the price used.
     */
    private function recordConversions(?int $priceCents): void
    {
        if ($priceCents === null) {
            return;
        }

        $price = Btc::usd($priceCents);
        $now = now();

        DB::table('users')->where('balance_micros', '>', 0)->orderBy('id')->chunkById(500, function ($users) use ($price, $now) {
            foreach ($users as $user) {
                DB::table('ledger_entries')->insert([
                    'user_id' => $user->id,
                    'type' => 'conversion',
                    'amount_micros' => 0,
                    'balance_after_micros' => $user->balance_micros,
                    'description' => 'Balance converted from BTC to USDT at '.$price.' per BTC',
                    'created_at' => $now,
                ]);
            }
        });
    }

    private function convertSettings(?int $priceCents): void
    {
        $settings = DB::table('settings')->pluck('value', 'key');

        $address = trim((string) ($settings['deposit_address'] ?? ''));

        if ($address !== '') {
            $walletId = DB::table('deposit_wallets')->insertGetId([
                'coin' => 'BTC',
                'network' => 'Bitcoin',
                'address' => $address,
                'explorer_tx_url' => $settings['explorer_tx_url'] ?? 'https://mempool.space/tx/',
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Every earlier deposit went to this address, so link them for their blockchain links.
            DB::table('deposits')->whereNull('deposit_wallet_id')->update(['deposit_wallet_id' => $walletId]);
        }

        foreach (['min_deposit' => 10, 'min_withdrawal' => 10] as $name => $defaultUsdt) {
            $sats = $settings[$name.'_sats'] ?? null;
            $micros = $sats !== null && $priceCents !== null
                ? max(Usdt::MICROS_PER_USDT, (int) round(Usdt::fromSats((int) $sats, $priceCents) / Usdt::MICROS_PER_USDT) * Usdt::MICROS_PER_USDT)
                : $defaultUsdt * Usdt::MICROS_PER_USDT;

            if ($sats !== null) {
                DB::table('settings')->updateOrInsert(['key' => $name.'_micros'], ['value' => (string) $micros, 'updated_at' => now()]);
            }
        }

        DB::table('settings')->whereIn('key', ['deposit_address', 'explorer_tx_url', 'min_deposit_sats', 'min_withdrawal_sats'])->delete();
        Cache::forget('site-settings');
    }
};
