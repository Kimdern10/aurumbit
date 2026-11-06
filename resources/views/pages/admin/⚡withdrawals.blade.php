<?php

use App\Enums\RequestStatus;
use App\Exceptions\WalletException;
use App\Models\Withdrawal;
use App\Services\BybitAccount;
use App\Services\WalletService;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Withdrawals')] class extends Component {
    use WithPagination;

    #[Url]
    public string $status = 'pending';

    public ?int $reviewingId = null;

    public string $decision = 'approve';

    public string $note = '';

    public string $payoutTxid = '';

    /**
     * The Bybit payout found for the withdrawal being approved, if any.
     *
     * @var array{txid: string, amount: string, coin: string, chain: string}|null
     */
    public ?array $bybitPayout = null;

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Withdrawal>
     */
    #[Computed]
    public function withdrawals(): LengthAwarePaginator
    {
        return Withdrawal::query()->with(['user:id,name,email', 'user.latestKyc'])
            ->when(RequestStatus::tryFrom($this->status), fn ($query, RequestStatus $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(20);
    }

    public function review(int $withdrawalId, string $decision, BybitAccount $bybit): void
    {
        Gate::authorize('manage-money');

        $this->reviewingId = $withdrawalId;
        $this->decision = $decision === 'reject' ? 'reject' : 'approve';
        $this->note = '';
        $this->payoutTxid = '';
        $this->bybitPayout = null;
        $this->resetValidation();

        if ($this->decision === 'approve') {
            $this->findBybitPayout($bybit);
        }

        Flux::modal('review-withdrawal')->show();
    }

    /**
     * Look in the Bybit withdrawal history for the payout to this customer's
     * address and fill in its blockchain transaction ID.
     */
    public function findBybitPayout(BybitAccount $bybit): void
    {
        $bybit->refresh();
        $this->bybitPayout = $bybit->findPayoutFor(Withdrawal::query()->findOrFail($this->reviewingId));

        if ($this->bybitPayout) {
            $this->payoutTxid = $this->bybitPayout['txid'];
        }
    }

    public function confirm(WalletService $wallet): void
    {
        Gate::authorize('manage-money');

        $withdrawal = Withdrawal::query()->findOrFail($this->reviewingId);
        $this->payoutTxid = $withdrawal->network->normalizeTxid($this->payoutTxid);

        $this->validate([
            'note' => [$this->decision === 'reject' ? 'required' : 'nullable', 'string', 'max:500'],
            'payoutTxid' => ['nullable', 'string', 'max:120'],
        ]);

        if ($this->decision === 'approve' && filled($this->payoutTxid) && ! $withdrawal->network->isValidTxid($this->payoutTxid)) {
            $this->addError('payoutTxid', __($withdrawal->network->txidHint()));

            return;
        }

        try {
            if ($this->decision === 'reject') {
                $wallet->rejectWithdrawal($withdrawal, Auth::user(), $this->note);
                Flux::toast(text: __('Withdrawal rejected and the amount returned.'));
            } else {
                $wallet->approveWithdrawal($withdrawal, Auth::user(), $this->payoutTxid, $this->note ?: null);
                Flux::toast(variant: 'success', text: __('Withdrawal marked as paid.'));
            }
        } catch (WalletException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }

        Flux::modal('review-withdrawal')->close();
        unset($this->withdrawals);
    }
};
?>

@php($canManageMoney = auth()->user()->can('manage-money'))

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Withdrawals') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Send the coin shown from the company wallet first, then approve it here. The site never sends coins by itself.') }}</flux:text>
        </div>
        <flux:select wire:model.live="status" class="sm:max-w-48">
            <flux:select.option value="pending">{{ __('Pending') }}</flux:select.option>
            <flux:select.option value="approved">{{ __('Approved') }}</flux:select.option>
            <flux:select.option value="rejected">{{ __('Rejected') }}</flux:select.option>
            <flux:select.option value="all">{{ __('All') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:card class="!p-0 overflow-hidden">
        <flux:table :paginate="$this->withdrawals" class="[&_td]:px-4! [&_th]:px-4!">
            <flux:table.columns>
                <flux:table.column>{{ __('User') }}</flux:table.column>
                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Address') }}</flux:table.column>
                <flux:table.column>{{ __('Requested') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->withdrawals as $withdrawal)
                    <flux:table.row :key="$withdrawal->id">
                        <flux:table.cell>
                            <a href="{{ route('admin.users.show', $withdrawal->user_id) }}" wire:navigate class="font-medium text-zinc-900 hover:underline dark:text-white">{{ $withdrawal->user->name }}</a>
                            <p class="text-xs text-zinc-500">{{ __('Identity:') }} {{ $withdrawal->user->kycStatus()?->label() ?? __('not sent') }}</p>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">
                            <span class="font-medium tabular-nums">{{ __('Send :amount', ['amount' => $withdrawal->payoutLabel()]) }}</span> <a href="{{ $withdrawal->receiptUrl(true) }}" class="font-mono text-[11px] text-brand-600 underline">{{ $withdrawal->code() }}</a>
                            @if ($withdrawal->coin !== 'USDT')
                                <p class="text-xs text-zinc-500"><x-usdt :micros="$withdrawal->amount_micros" />@if ($withdrawal->price_cents) {{ __('at :price USDT per :coin', ['price' => \App\Support\Usdt::format($withdrawal->price_cents * 10_000), 'coin' => $withdrawal->coin]) }}@endif</p>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="max-w-56">
                            <flux:badge size="sm">{{ $withdrawal->network->label() }}</flux:badge>
                            <span class="mt-1 block truncate font-mono text-xs" title="{{ $withdrawal->address }}">{{ $withdrawal->address }}</span>
                            @if ($withdrawal->status === RequestStatus::Pending)
                                <button type="button" data-copy="{{ $withdrawal->address }}" class="text-xs underline"><span data-copy-label>{{ __('Copy address') }}</span></button>
                            @elseif ($url = $withdrawal->payoutUrl())
                                <a href="{{ $url }}" target="_blank" rel="noopener" class="text-xs underline">{{ __('View payment') }}</a>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ $withdrawal->created_at->format('M j, g:i A') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$withdrawal->status->color()">{{ $withdrawal->status->label() }}</flux:badge>
                            @if ($withdrawal->admin_note) <p class="mt-1 max-w-48 truncate text-xs text-zinc-500">{{ $withdrawal->admin_note }}</p> @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($withdrawal->status === RequestStatus::Pending && $canManageMoney)
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" variant="primary" wire:click="review({{ $withdrawal->id }}, 'approve')">{{ __('Approve') }}</flux:button>
                                    <flux:button size="sm" variant="danger" wire:click="review({{ $withdrawal->id }}, 'reject')">{{ __('Reject') }}</flux:button>
                                </div>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="6" class="text-center">{{ __('Nothing here.') }}</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="review-withdrawal" class="w-full max-w-md">
        <form wire:submit="confirm" class="space-y-4">
            <flux:heading size="lg">{{ $decision === 'reject' ? __('Reject withdrawal') : __('Approve withdrawal') }}</flux:heading>
            @if ($decision === 'reject')
                <flux:text class="text-sm">{{ __('The full amount goes back to the user\'s balance.') }}</flux:text>
            @else
                <flux:text class="text-sm">{{ __('Send the exact amount shown, in the coin and on the network shown, to the user\'s address from the company wallet.') }}</flux:text>
                @if ($bybitPayout)
                    <flux:callout color="green" icon="check-badge" data-bybit-payout>
                        <flux:callout.text>{{ __('Found in your Bybit withdrawals: :amount :coin on :chain to this address. Its transaction ID is filled in below.', ['amount' => $bybitPayout['amount'], 'coin' => $bybitPayout['coin'], 'chain' => $bybitPayout['chain']]) }}</flux:callout.text>
                    </flux:callout>
                @elseif (app(BybitAccount::class)->isConfigured())
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-zinc-50 px-3 py-2 text-xs text-zinc-500 dark:bg-white/5">
                        <span>{{ __('No Bybit payout to this address yet. Send it from Bybit, then check again.') }}</span>
                        <flux:button size="xs" icon="arrow-path" wire:click="findBybitPayout">{{ __('Check Bybit again') }}</flux:button>
                    </div>
                @endif
                <flux:input wire:model="payoutTxid" :label="__('Payment transaction ID (optional)')" class="font-mono" autocomplete="off" spellcheck="false"
                    :description="__('Filled in automatically when the payout was sent from Bybit. Otherwise, in Bybit open Assets → History → Withdrawals, open the payout and copy its TxID (transaction hash).')" />
            @endif
            <flux:textarea wire:model="note" :label="$decision === 'reject' ? __('Reason (the user will see this)') : __('Note (optional)')" rows="3" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" :variant="$decision === 'reject' ? 'danger' : 'primary'">{{ $decision === 'reject' ? __('Reject') : __('Approve') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
