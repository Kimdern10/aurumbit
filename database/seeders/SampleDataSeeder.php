<?php

namespace Database\Seeders;

use App\Enums\WithdrawalNetwork;
use App\Models\Announcement;
use App\Models\Deposit;
use App\Models\DepositWallet;
use App\Models\KycSubmission;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

/**
 * Sample admin and users with a believable history, for local testing only.
 * Logins: admin@hashvale.test and emma@example.com etc., password "password".
 */
class SampleDataSeeder extends Seeder
{
    public function run(WalletService $wallet): void
    {
        if (User::query()->where('email', 'admin@hashvale.test')->exists()) {
            return;
        }

        $admin = User::factory()->admin()->create(['name' => 'Hashvale Admin', 'email' => 'admin@hashvale.test']);

        Setting::put(['company_details' => 'Hashvale Inc., a Delaware corporation. Registration details to be added.']);

        // Sample addresses for local testing only. The real company wallets are added under Deposit wallets.
        $usdt = DepositWallet::factory()->create(['sort_order' => 0]);
        $btc = DepositWallet::factory()->bitcoin()->create(['sort_order' => 1]);
        DepositWallet::factory()->create(['coin' => 'ETH', 'network' => 'Ethereum', 'address' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', 'explorer_tx_url' => 'https://etherscan.io/tx/', 'sort_order' => 2]);

        $plans = Plan::query()->get()->keyBy('slug');

        $emma = User::factory()->create(['name' => 'Emma Carter', 'email' => 'emma@example.com']);
        $james = User::factory()->create(['name' => 'James Miller', 'email' => 'james@example.com', 'referred_by_id' => $emma->id]);
        $olivia = User::factory()->create(['name' => 'Olivia Brooks', 'email' => 'olivia@example.com', 'referred_by_id' => $emma->id]);
        User::factory()->create(['name' => 'Noah Bennett', 'email' => 'noah@example.com']);

        $this->kyc($emma, approved: true, reviewer: $admin);
        $this->kyc($james, approved: true, reviewer: $admin);
        $this->kyc($olivia, approved: false);

        // Emma: deposits, two plans (one finished), a paid withdrawal and a pending one.
        $this->at(40, fn () => $wallet->approveDeposit($wallet->requestDeposit($emma, $usdt, '20000', str_repeat('a1', 32)), $admin, 20_000_000_000));
        $this->at(38, fn () => $wallet->invest($emma, $plans['bitcoin-starter'], 4_000_000_000));
        $this->at(30, fn () => $wallet->invest($emma, $plans['gold-plus'], 6_500_000_000));
        $this->at(23, fn () => $wallet->settle($emma->investments()->where('plan_id', $plans['bitcoin-starter']->id)->firstOrFail()));
        $this->at(20, fn () => $wallet->approveWithdrawal($wallet->requestWithdrawal($emma, 1_500_000_000, WithdrawalNetwork::Trc20, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'), $admin, str_repeat('e5', 32)));
        $this->at(12, fn () => $wallet->approveDeposit($wallet->requestDeposit($emma, $btc, '0.1', str_repeat('b2', 32)), $admin, 7_672_800_000));
        $this->at(6, fn () => $wallet->invest($emma, $plans['bitcoin-plus'], 5_000_000_000));
        $this->at(1, fn () => $wallet->requestWithdrawal($emma, 1_200_000_000, WithdrawalNetwork::Erc20, '0x742d35Cc6634C0532925a3b844Bc454e4438f44e'));

        // James (invited by Emma): first deposit pays Emma a referral bonus.
        $this->at(15, fn () => $wallet->approveDeposit($wallet->requestDeposit($james, $usdt, '9500', str_repeat('d4', 32)), $admin, 9_500_000_000));
        $this->at(14, fn () => $wallet->invest($james, $plans['gold-starter'], 3_000_000_000));
        $this->at(3, fn () => $wallet->rejectWithdrawal($wallet->requestWithdrawal($james, 800_000_000, WithdrawalNetwork::Trc20, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'), $admin, 'Please confirm this address belongs to you.'));

        // Waiting for the admin.
        $this->at(2, fn () => $wallet->requestDeposit($olivia, $btc, '0.03', str_repeat('c3', 32)));
        $this->at(0, fn () => Deposit::factory()->for($james)->create(['deposit_wallet_id' => $usdt->id, 'coin_amount' => '600']));

        $wallet->accrue($emma->investments()->where('plan_id', $plans['gold-plus']->id)->firstOrFail());
        $wallet->accrue($emma->investments()->where('plan_id', $plans['bitcoin-plus']->id)->firstOrFail());
        $wallet->accrue($james->investments()->firstOrFail());

        Announcement::factory()->create([
            'title' => 'Welcome to Hashvale',
            'body' => 'Verify your identity, fund your wallet and use the calculator on the Plans page to see what each plan earns before you invest.',
            'level' => 'info',
        ]);
    }

    /**
     * Run a step as if it happened some days ago, so charts and progress bars have history.
     */
    private function at(int $daysAgo, callable $step): void
    {
        Date::setTestNow(now()->subDays($daysAgo)->setTime(random_int(9, 18), random_int(0, 59)));
        $step();
        Date::setTestNow();
    }

    private function kyc(User $user, bool $approved, ?User $reviewer = null): void
    {
        $folder = 'kyc/'.$user->id;
        $document = $folder.'/document.png';
        $selfie = $folder.'/selfie.png';

        Storage::disk('local')->put($document, $this->placeholderImage('ID DOCUMENT'));
        Storage::disk('local')->put($selfie, $this->placeholderImage('SELFIE'));

        $submission = KycSubmission::factory()->for($user)->create([
            'full_name' => $user->name,
            'document_type' => 'drivers_license',
            'document_path' => $document,
            'selfie_path' => $selfie,
        ]);

        if ($approved) {
            $submission->forceFill(['status' => 'approved', 'reviewed_by' => $reviewer?->id, 'reviewed_at' => now()])->save();
        }
    }

    private function placeholderImage(string $label): string
    {
        $image = imagecreatetruecolor(480, 300);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 226, 230, 239));
        imagestring($image, 5, 30, 140, 'DEMO '.$label.' - NOT A REAL DOCUMENT', (int) imagecolorallocate($image, 11, 27, 58));

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
