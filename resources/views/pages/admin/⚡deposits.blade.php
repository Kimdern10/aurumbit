<?php

use App\Enums\RequestStatus;
use App\Exceptions\WalletException;
use App\Models\Deposit;
use App\Models\DepositWallet;
use App\Services\BybitAccount;
use App\Services\DepositAutoCredit;
use App\Services\MarketPrices;
use App\Services\WalletService;
use App\Support\Usdt;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Deposits')] class extends Component {
    use WithPagination;

    #[Url]
    public string $status = 'pending';

    public ?int $rejectingId = null;

    public string $note = '';

    public ?int $approvingId = null;

    public string $creditUsdt = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Deposit>
     */
    #[Computed]
    public function deposits(): LengthAwarePaginator
    {
        return Deposit::query()->with(['user:id,name,email', 'wallet'])
            ->when(RequestStatus::tryFrom($this->status), fn ($query, RequestStatus $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(20);
    }

    #[Computed]
    public function approving(): ?Deposit
    {
        return $this->approvingId ? Deposit::query()->with('user:id,name')->find($this->approvingId) : null;
    }

    /**
     * Open the approve dialog with the USDT value at today's price filled in.
     * When Bybit received a different amount under the same transaction ID,
     * the amount Bybit actually received is used instead of the customer's figure.
     */
    public function startApprove(int $depositId, MarketPrices $prices, BybitAccount $bybit): void
    {
        Gate::authorize('manage-money');

        $this->approvingId = $depositId;
        unset($this->approving);
        $check = $this->approving ? $bybit->checkDeposit($this->approving) : null;
        $priceCents = $this->approving ? DepositWallet::priceCentsFor($this->approving->coin, $prices) : null;
        $estimate = $check && $check['state'] === 'different' && $priceCents && strcasecmp($check['bybit']['coin'], $this->approving->coin) === 0
            ? Usdt::fromCoin(BybitAccount::plainAmount($check['bybit']['amount']), $priceCents)
            : $this->approving?->estimatedMicros($prices);
        $this->creditUsdt = $estimate ? str_replace(',', '', Usdt::format($estimate)) : '';
        $this->resetValidation();
        Flux::modal('approve-deposit')->show();
    }

    /**
     * Run auto-credit now instead of waiting for the next scheduled check.
     */
    public function autoCreditNow(DepositAutoCredit $autoCredit, BybitAccount $bybit): void
    {
        Gate::authorize('manage-money');

        $bybit->refresh();
        $counts = $autoCredit->run();
        unset($this->deposits);

        Flux::toast(text: __(':credited credited automatically, :left left for you to review.', $counts));
    }

    public function approve(WalletService $wallet): void
    {
        Gate::authorize('manage-money');

        $this->validate(['creditUsdt' => ['required', 'regex:'.Usdt::INPUT_PATTERN]], [
            'creditUsdt.regex' => __('Enter a USDT amount, with up to 2 decimal places.'),
        ]);

        try {
            $wallet->approveDeposit(Deposit::query()->findOrFail($this->approvingId), Auth::user(), (int) Usdt::toMicros($this->creditUsdt));
            Flux::toast(variant: 'success', text: __('Deposit credited.'));
        } catch (WalletException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }

        Flux::modal('approve-deposit')->close();
        unset($this->deposits);
    }

    public function startReject(int $depositId): void
    {
        Gate::authorize('manage-money');

        $this->rejectingId = $depositId;
        $this->note = '';
        Flux::modal('reject-deposit')->show();
    }

    public function reject(WalletService $wallet): void
    {
        Gate::authorize('manage-money');

        $this->validate(['note' => ['required', 'string', 'max:500']]);

        try {
            $wallet->rejectDeposit(Deposit::query()->findOrFail($this->rejectingId), Auth::user(), $this->note);
            Flux::toast(text: __('Deposit rejected.'));
        } catch (WalletException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }

        Flux::modal('reject-deposit')->close();
        unset($this->deposits);
    }
};
?>

@php($canManageMoney = auth()->user()->can('manage-money'))

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Deposits') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Open each transaction on the blockchain and check the amount reached our address with enough confirmations before you approve. Approved deposits are credited in USDT.') }}</flux:text>
        </div>
        <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
            @if ($canManageMoney && DepositAutoCredit::isEnabled())
                <flux:button icon="bolt" wire:click="autoCreditNow" data-auto-credit-now>{{ __('Credit confirmed now') }}</flux:button>
            @endif
            <flux:select wire:model.live="status" class="sm:max-w-48">
                <flux:select.option value="pending">{{ __('Pending') }}</flux:select.option>
                <flux:select.option value="approved">{{ __('Approved') }}</flux:select.option>
                <flux:select.option value="rejected">{{ __('Rejected') }}</flux:select.option>
                <flux:select.option value="all">{{ __('All') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    <flux:card class="!p-0 overflow-hidden">
        <flux:table :paginate="$this->deposits" class="[&_td]:px-4! [&_th]:px-4!">
            <flux:table.columns>
                <flux:table.column>{{ __('User') }}</flux:table.column>
                <flux:table.column>{{ __('Sent') }}</flux:table.column>
                <flux:table.column>{{ __('Credited') }}</flux:table.column>
                <flux:table.column>{{ __('Reference') }}</flux:table.column>
                <flux:table.column>{{ __('Sent') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->deposits as $deposit)
                    <flux:table.row :key="$deposit->id">
                        <flux:table.cell>
                            <a href="{{ route('admin.users.show', $deposit->user_id) }}" wire:navigate class="font-medium text-zinc-900 hover:underline dark:text-white">{{ $deposit->user->name }}</a>
                            <p class="text-xs text-zinc-500">{{ $deposit->user->email }}</p>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">
                            <span class="font-medium tabular-nums">{{ $deposit->coinLabel() }}</span> <a href="{{ $deposit->receiptUrl(true) }}" class="font-mono text-[11px] text-brand-600 underline">{{ $deposit->code() }}</a>
                            <p class="text-xs text-zinc-500">{{ $deposit->network }}</p>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($deposit->credited_micros !== null)
                                <x-usdt :micros="$deposit->credited_micros" />
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="max-w-40 font-mono text-xs">
                            @if ($url = $deposit->explorerUrl())
                                <a href="{{ $url }}" target="_blank" rel="noopener" class="block truncate underline" title="{{ $deposit->reference }}">{{ __('Check on blockchain') }}</a>
                            @elseif ($deposit->reference)
                                <span class="block truncate" title="{{ $deposit->reference }}">{{ $deposit->reference }}</span>
                            @else
                                —
                            @endif
                            <x-bybit-check :check="app(\App\Services\BybitAccount::class)->checkDeposit($deposit)" class="mt-1 font-sans" />
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ $deposit->created_at->format('M j, g:i A') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$deposit->status->color()">{{ $deposit->status->label() }}</flux:badge>
                            @if ($deposit->admin_note) <p class="mt-1 max-w-48 truncate text-xs text-zinc-500">{{ $deposit->admin_note }}</p> @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($deposit->status === RequestStatus::Pending && $canManageMoney)
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" variant="primary" wire:click="startApprove({{ $deposit->id }})">{{ __('Approve') }}</flux:button>
                                    <flux:button size="sm" variant="danger" wire:click="startReject({{ $deposit->id }})">{{ __('Reject') }}</flux:button>
                                </div>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="7" class="text-center">{{ __('Nothing here.') }}</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="approve-deposit" class="w-full max-w-md">
        <form wire:submit="approve" class="space-y-4">
            <flux:heading size="lg">{{ __('Credit deposit') }}</flux:heading>
            @if ($this->approving)
                <flux:text>{{ __(':user sent :amount on :network.', ['user' => $this->approving->user->name, 'amount' => $this->approving->coinLabel(), 'network' => $this->approving->network]) }}</flux:text>
                <x-bybit-check :check="app(\App\Services\BybitAccount::class)->checkDeposit($this->approving)" detailed />
            @endif
            <flux:input wire:model="creditUsdt" :label="__('USDT to credit')" inputmode="decimal"
                :description="__('Filled in at today\'s price when we have one for this coin. Check the transaction on the blockchain, then adjust if needed.')" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Credit to user') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="reject-deposit" class="w-full max-w-md">
        <form wire:submit="reject" class="space-y-4">
            <flux:heading size="lg">{{ __('Reject deposit') }}</flux:heading>
            <flux:textarea wire:model="note" :label="__('Reason (the user will see this)')" rows="3" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Reject') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
