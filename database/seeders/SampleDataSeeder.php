<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Deposit;
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
 * Logins: admin@aurumbit.test and ada@example.com etc., password "password".
 */
class SampleDataSeeder extends Seeder
{
    public function run(WalletService $wallet): void
    {
        if (User::query()->where('email', 'admin@aurumbit.test')->exists()) {
            return;
        }

        $admin = User::factory()->admin()->create(['name' => 'AurumBit Admin', 'email' => 'admin@aurumbit.test']);

        // A sample address for local testing only. The real company address is set in Site settings.
        Setting::put(['deposit_address' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq', 'company_details' => 'AurumBit Ltd. RC 0000000. SEC registration number: to be added.']);

        $plans = Plan::query()->get()->keyBy('slug');

        $ada = User::factory()->create(['name' => 'Ada Okafor', 'email' => 'ada@example.com']);
        $tunde = User::factory()->create(['name' => 'Tunde Bello', 'email' => 'tunde@example.com', 'referred_by_id' => $ada->id]);
        $chioma = User::factory()->create(['name' => 'Chioma Eze', 'email' => 'chioma@example.com', 'referred_by_id' => $ada->id]);
        User::factory()->create(['name' => 'Emeka Nwosu', 'email' => 'emeka@example.com']);

        $this->kyc($ada, approved: true, reviewer: $admin);
        $this->kyc($tunde, approved: true, reviewer: $admin);
        $this->kyc($chioma, approved: false);

        // Ada: deposits, two plans (one finished), a paid withdrawal and a pending one.
        $this->at(40, fn () => $wallet->approveDeposit($wallet->requestDeposit($ada, 25_000_000, str_repeat('a1', 32)), $admin));
        $this->at(38, fn () => $wallet->invest($ada, $plans['hashrate-starter'], 5_000_000));
        $this->at(30, fn () => $wallet->invest($ada, $plans['hard-rock-gold'], 8_000_000));
        $this->at(23, fn () => $wallet->settle($ada->investments()->where('plan_id', $plans['hashrate-starter']->id)->firstOrFail()));
        $this->at(20, fn () => $wallet->approveWithdrawal($wallet->requestWithdrawal($ada, 2_000_000, 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq'), $admin, str_repeat('e5', 32)));
        $this->at(12, fn () => $wallet->approveDeposit($wallet->requestDeposit($ada, 10_000_000, str_repeat('b2', 32)), $admin));
        $this->at(6, fn () => $wallet->invest($ada, $plans['hashrate-core'], 6_000_000));
        $this->at(1, fn () => $wallet->requestWithdrawal($ada, 1_500_000, '3J98t1WpEZ73CNmQviecrnyiWrnqRhWNLy'));

        // Tunde (invited by Ada): first deposit pays Ada a referral bonus.
        $this->at(15, fn () => $wallet->approveDeposit($wallet->requestDeposit($tunde, 12_000_000, str_repeat('d4', 32)), $admin));
        $this->at(14, fn () => $wallet->invest($tunde, $plans['alluvial-gold'], 4_000_000));
        $this->at(3, fn () => $wallet->rejectWithdrawal($wallet->requestWithdrawal($tunde, 1_000_000, 'bc1p5d7rjq7g6rdk2yhzks9smlaqtedr4dekq08ge8ztwac72sfr9rusxg3297'), $admin, 'Please confirm this address belongs to you.'));

        // Waiting for the admin.
        $this->at(2, fn () => $wallet->requestDeposit($chioma, 3_000_000, str_repeat('c3', 32)));
        $this->at(0, fn () => Deposit::factory()->for($tunde)->create(['amount_sats' => 750_000]));

        $wallet->accrue($ada->investments()->where('plan_id', $plans['hard-rock-gold']->id)->firstOrFail());
        $wallet->accrue($ada->investments()->where('plan_id', $plans['hashrate-core']->id)->firstOrFail());
        $wallet->accrue($tunde->investments()->firstOrFail());

        Announcement::factory()->create([
            'title' => 'Welcome to AurumBit',
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
            'document_type' => 'national_id',
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
