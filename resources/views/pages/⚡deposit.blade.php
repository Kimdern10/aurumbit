<?php

use App\Exceptions\WalletException;
use App\Models\Deposit;
use App\Models\Setting;
use App\Services\WalletService;
use App\Support\Btc;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Deposit')] class extends Component {
    use WithPagination;

    public string $amount = '';

    public string $reference = '';

    /**
     * @return LengthAwarePaginator<int, Deposit>
     */
    #[Computed]
    public function deposits(): LengthAwarePaginator
    {
        return Auth::user()->deposits()->latest('id')->paginate(8);
    }

    public function save(WalletService $wallet): void
    {
        $this->validate([
            'amount' => ['required', 'regex:'.Btc::INPUT_PATTERN],
            'reference' => ['nullable', 'string', 'max:120'],
        ], [
            'amount.regex' => __('Enter an amount in BTC, with up to 8 decimal places.'),
        ]);

        try {
            $wallet->requestDeposit(Auth::user(), (int) Btc::toSats($this->amount), $this->reference ?: null);
        } catch (WalletException $exception) {
            $this->addError('amount', $exception->getMessage());

            return;
        }

        $this->reset('amount', 'reference');
        $this->resetPage();
        Flux::toast(variant: 'success', text: __('Deposit recorded. An admin will review it.'));
    }
};
?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Deposit') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Record a simulated Bitcoin deposit. An admin credits it after review.') }}</flux:text>
    </div>

    <flux:callout color="amber" icon="exclamation-triangle">
        <flux:callout.heading>{{ __('Do not send real Bitcoin') }}</flux:callout.heading>
        <flux:callout.text>{{ __('This is a demo platform. The address below is not a real wallet and cannot receive coins. Deposits here are simulated.') }}</flux:callout.text>
    </flux:callout>

    <div class="grid gap-6 lg:grid-cols-5">
        <flux:card class="flex flex-col items-center gap-4 text-center lg:col-span-2">
            <flux:heading>{{ __('Demo deposit address') }}</flux:heading>
            <x-demo-qr :value="Setting::get('demo_deposit_address')" class="border border-zinc-200 dark:border-transparent" />
            <code class="w-full rounded-lg bg-zinc-100 px-3 py-2 text-xs break-all text-zinc-800 dark:bg-white/10 dark:text-zinc-200">{{ Setting::get('demo_deposit_address') }}</code>
            <flux:button size="sm" icon="clipboard-document" data-copy="{{ Setting::get('demo_deposit_address') }}"><span data-copy-label>{{ __('Copy address') }}</span></flux:button>
            <flux:text class="text-xs">{{ __('Network: Bitcoin. Pattern is for display only and cannot be scanned.') }}</flux:text>
        </flux:card>

        <flux:card class="lg:col-span-3">
            <form wire:submit="save" class="space-y-5">
                <flux:heading>{{ __('Record a deposit') }}</flux:heading>
                <flux:input wire:model="amount" :label="__('Amount (BTC)')" inputmode="decimal" autocomplete="off" placeholder="0.01"
                    :description="__('Minimum :min BTC', ['min' => Btc::format(Setting::integer('min_deposit_sats'))])" />
                <flux:input wire:model="reference" :label="__('Transaction reference (optional)')" :description="__('For a real deposit this would be the transaction ID. In the demo you can leave it empty.')" />
                <flux:button type="submit" variant="primary">{{ __('Submit deposit') }}</flux:button>
            </form>
        </flux:card>
    </div>

    <flux:card class="!p-0 overflow-hidden">
        <div class="px-4 pt-4"><flux:heading>{{ __('Your deposits') }}</flux:heading></div>
        <flux:table :paginate="$this->deposits" class="[&_td]:px-4! [&_th]:px-4!">
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Note') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->deposits as $deposit)
                    <flux:table.row :key="$deposit->id">
                        <flux:table.cell>{{ $deposit->created_at->format('j M Y, H:i') }}</flux:table.cell>
                        <flux:table.cell><x-btc :sats="$deposit->amount_sats" /></flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$deposit->status->color()">{{ $deposit->status->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell class="max-w-56 truncate">{{ $deposit->admin_note }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center">{{ __('No deposits yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
