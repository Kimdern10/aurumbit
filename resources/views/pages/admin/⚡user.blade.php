<?php

use App\Exceptions\WalletException;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Notifications\AccountActivity;
use App\Services\WalletService;
use App\Support\Usdt;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('User')] class extends Component {
    use WithPagination;

    /**
     * Suspension lengths the admin can pick, in days. "forever" means until an admin lifts it.
     */
    public const array SUSPENSION_LENGTHS = ['1' => '1 day', '7' => '7 days', '30' => '30 days', 'forever' => 'Until I lift it'];

    public User $user;

    public string $adjustAmount = '';

    public string $adjustReason = '';

    public string $blockAction = 'suspend';

    public string $suspendFor = '7';

    public string $blockReason = '';

    /**
     * @return LengthAwarePaginator<int, LedgerEntry>
     */
    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        return $this->user->ledgerEntries()->with('actor:id,name')->latest('id')->paginate(15);
    }

    public function adjust(WalletService $wallet): void
    {
        $this->validate([
            'adjustAmount' => ['required', 'regex:/^-?\d{1,9}(\.\d{1,2})?$/'],
            'adjustReason' => ['required', 'string', 'min:5', 'max:200'],
        ], [
            'adjustAmount.regex' => __('Enter a USDT amount, e.g. 50 to add or -50 to take away.'),
        ]);

        $negative = str_starts_with($this->adjustAmount, '-');
        $micros = (int) Usdt::toMicros(ltrim($this->adjustAmount, '-'));

        try {
            $wallet->adjust($this->user, $negative ? -$micros : $micros, $this->adjustReason, Auth::user());
        } catch (WalletException $exception) {
            $this->addError('adjustAmount', $exception->getMessage());

            return;
        }

        $this->user->refresh();
        unset($this->entries);
        $this->reset('adjustAmount', 'adjustReason');
        Flux::toast(variant: 'success', text: __('Balance adjusted and logged.'));
    }

    public function openBlock(string $action): void
    {
        $this->blockAction = $action === 'ban' ? 'ban' : 'suspend';
        $this->reset('suspendFor', 'blockReason');
        $this->resetValidation();
        Flux::modal('block-user')->show();
    }

    /**
     * Suspend or ban the user. They are signed out on their next page load and told why.
     */
    public function block(): void
    {
        abort_if($this->user->is(Auth::user()), 422, 'You cannot suspend or ban yourself.');

        $this->validate([
            'blockAction' => ['required', Rule::in(['suspend', 'ban'])],
            'suspendFor' => [Rule::requiredIf($this->blockAction === 'suspend'), Rule::in(array_keys(self::SUSPENSION_LENGTHS))],
            'blockReason' => [$this->blockAction === 'ban' ? 'required' : 'nullable', 'string', 'max:500'],
        ]);

        $reason = filled($this->blockReason) ? trim($this->blockReason) : null;

        if ($this->blockAction === 'ban') {
            $this->user->ban($reason);
            Flux::toast(variant: 'danger', text: __('User banned.'));
        } else {
            $this->user->suspend($this->suspendFor === 'forever' ? null : now()->addDays((int) $this->suspendFor), $reason);
            Flux::toast(text: __('User suspended.'));
        }

        $this->user->notify(new AccountActivity($this->blockAction === 'ban' ? 'Account banned' : 'Account suspended', $this->user->blockedMessage(), null, 'danger'));

        Flux::modal('block-user')->close();
    }

    public function restore(): void
    {
        $this->user->restore();
        $this->user->notify(new AccountActivity('Account restored', 'Your account is active again. You can sign in as usual.', route('dashboard'), 'success'));
        Flux::toast(variant: 'success', text: __('User restored. They can sign in again.'));
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:link :href="route('admin.users')" wire:navigate class="text-sm">← {{ __('All users') }}</flux:link>
            <flux:heading size="xl" class="mt-2">{{ $user->name }}</flux:heading>
            <flux:text>{{ $user->email }} · {{ __('Joined :date', ['date' => $user->created_at->format('M j, Y')]) }} · {{ __('Code') }} <span class="font-mono">{{ $user->referral_code }}</span></flux:text>
            <div class="mt-2 flex flex-wrap gap-1.5">
                <flux:badge size="sm" :color="$user->hasVerifiedEmail() ? 'green' : 'zinc'">{{ $user->hasVerifiedEmail() ? __('Email verified') : __('Email not verified') }}</flux:badge>
                <flux:badge size="sm" :color="$user->two_factor_confirmed_at ? 'green' : 'zinc'">{{ $user->two_factor_confirmed_at ? __('Two-step on') : __('Two-step off') }}</flux:badge>
                @if ($user->kycStatus()) <flux:badge size="sm" :color="$user->kycStatus()->color()">{{ __('Identity: :status', ['status' => $user->kycStatus()->label()]) }}</flux:badge> @endif
                <x-account-status-badge :user="$user" />
            </div>
        </div>
        @unless ($user->is(auth()->user()))
            <div class="flex flex-wrap gap-2">
                @if ($user->isBlocked())
                    <flux:button wire:click="restore" wire:confirm="{{ __('Lift the :status and let this user sign in again?', ['status' => $user->isBanned() ? __('ban') : __('suspension')]) }}" variant="primary" icon="lock-open">{{ __('Restore user') }}</flux:button>
                @else
                    <flux:button wire:click="openBlock('suspend')" icon="pause-circle">{{ __('Suspend') }}</flux:button>
                    <flux:button wire:click="openBlock('ban')" variant="danger" icon="no-symbol">{{ __('Ban') }}</flux:button>
                @endif
            </div>
        @endunless
    </div>

    @if ($user->isBlocked())
        <flux:callout :color="$user->isBanned() ? 'red' : 'amber'" :icon="$user->isBanned() ? 'no-symbol' : 'pause-circle'">
            <flux:callout.heading>
                @if ($user->isBanned())
                    {{ __('Banned on :date', ['date' => $user->banned_at->format('M j, Y g:i A')]) }}
                @elseif ($user->suspended_until)
                    {{ __('Suspended until :date', ['date' => $user->suspended_until->format('M j, Y g:i A')]) }}
                @else
                    {{ __('Suspended until an admin lifts it') }}
                @endif
            </flux:callout.heading>
            <flux:callout.text>{{ ($user->isBanned() ? $user->ban_reason : $user->suspension_reason) ?: __('No reason given.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card :label="__('Balance')" icon="wallet"><x-usdt :micros="$user->balance_micros" /></x-stat-card>
        <x-stat-card :label="__('In active plans')" icon="chart-pie"><x-usdt :micros="$user->investedSats()" /></x-stat-card>
        <x-stat-card :label="__('Invited users')" icon="users">{{ $user->referrals()->count() }}</x-stat-card>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <flux:card>
            <form wire:submit="adjust" class="space-y-4">
                <flux:heading>{{ __('Adjust balance') }}</flux:heading>
                <flux:text class="text-sm">{{ __('The customer sees this as a "Balance adjustment" with the description below. Your name is kept in the admin audit log only.') }}</flux:text>
                <flux:input wire:model="adjustAmount" :label="__('Amount (USDT)')" placeholder="50 or -50" inputmode="decimal" />
                <flux:input wire:model="adjustReason" :label="__('Description the customer sees')" :placeholder="__('e.g. Welcome bonus')" />
                <flux:button type="submit" variant="primary">{{ __('Apply adjustment') }}</flux:button>
            </form>
        </flux:card>

        <flux:card class="!p-0 overflow-hidden xl:col-span-2">
            <div class="px-4 pt-4"><flux:heading>{{ __('Recent balance changes') }}</flux:heading></div>
            <flux:table :paginate="$this->entries" class="[&_td]:px-4! [&_th]:px-4!">
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Details') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Balance after') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->entries as $entry)
                        <flux:table.row :key="$entry->id">
                            <flux:table.cell class="whitespace-nowrap">{{ $entry->created_at->format('M j, g:i A') }}</flux:table.cell>
                            <flux:table.cell>
                                <p>{{ $entry->description }}</p>
                                @if ($entry->actor) <p class="text-xs text-zinc-500">{{ __('by :name', ['name' => $entry->actor->name]) }}</p> @endif
                            </flux:table.cell>
                            <flux:table.cell align="end"><x-usdt :micros="$entry->amount_micros" signed /></flux:table.cell>
                            <flux:table.cell align="end"><x-usdt :micros="$entry->balance_after_micros" /></flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="4" class="text-center">{{ __('No changes yet.') }}</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>

    <flux:modal name="block-user" class="w-full max-w-md">
        <form wire:submit="block" class="space-y-4">
            <flux:heading size="lg">{{ $blockAction === 'ban' ? __('Ban :name', ['name' => $user->name]) : __('Suspend :name', ['name' => $user->name]) }}</flux:heading>
            <flux:text class="text-sm">
                {{ $blockAction === 'ban'
                    ? __('The account is closed for good: the user is signed out and cannot sign in again. Their balance and history are kept, and you can lift the ban later.')
                    : __('The user is signed out and cannot sign in until the suspension ends. Their balance and history are kept.') }}
            </flux:text>
            @if ($blockAction === 'suspend')
                <flux:radio.group wire:model="suspendFor" :label="__('Suspend for')" variant="segmented">
                    @foreach ($this::SUSPENSION_LENGTHS as $value => $label)
                        <flux:radio :value="$value" :label="__($label)" />
                    @endforeach
                </flux:radio.group>
            @endif
            <flux:textarea wire:model="blockReason" rows="3"
                :label="$blockAction === 'ban' ? __('Reason (the user will see this)') : __('Reason (optional, the user will see this)')" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" :variant="$blockAction === 'ban' ? 'danger' : 'primary'">{{ $blockAction === 'ban' ? __('Ban user') : __('Suspend user') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
