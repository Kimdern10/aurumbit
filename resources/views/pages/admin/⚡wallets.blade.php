<?php

use App\Models\DepositWallet;
use App\Rules\BitcoinAddress;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Deposit wallets')] class extends Component {
    use WithFileUploads, WithPagination;

    /**
     * Only owners may open this page or use its actions.
     */
    public function boot(): void
    {
        Gate::authorize('manage-money');
    }

    public ?int $editingId = null;

    /**
     * A new QR code image for the wallet being edited.
     */
    public ?TemporaryUploadedFile $qrImage = null;

    public bool $removeQr = false;

    public bool $showForm = false;

    public string $coin = 'USDT';

    public string $network = 'TRC20';

    public string $address = '';

    public string $explorerTxUrl = '';

    public bool $isActive = true;

    public int $sortOrder = 0;

    /**
     * @return LengthAwarePaginator<int, DepositWallet>
     */
    #[Computed]
    public function wallets(): LengthAwarePaginator
    {
        return DepositWallet::query()
            ->withCount(['deposits', 'deposits as pending_count' => fn ($query) => $query->where('status', 'pending')])
            ->withSum(['deposits as credited_micros' => fn ($query) => $query->where('status', 'approved')], 'credited_micros')
            ->orderBy('sort_order')->orderBy('id')
            ->paginate(10);
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'coin', 'network', 'address', 'explorerTxUrl', 'isActive', 'qrImage', 'removeQr');
        $this->sortOrder = (int) DepositWallet::query()->max('sort_order') + 1;
        $this->showForm = true;
    }

    public function edit(DepositWallet $wallet): void
    {
        $this->resetValidation();
        $this->editingId = $wallet->id;
        $this->coin = $wallet->coin;
        $this->network = $wallet->network;
        $this->address = $wallet->address;
        $this->explorerTxUrl = (string) $wallet->explorer_tx_url;
        $this->isActive = $wallet->is_active;
        $this->sortOrder = $wallet->sort_order;
        $this->reset('qrImage', 'removeQr');
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->coin = strtoupper(trim($this->coin));
        $this->network = trim($this->network);
        $this->address = trim($this->address);

        $validated = $this->validate([
            'coin' => ['required', 'string', 'max:12', 'regex:/^[A-Z0-9]+$/'],
            'network' => ['required', 'string', 'max:40'],
            'address' => ['required', 'string', 'min:20', 'max:200', 'regex:/^\S+$/'],
            'explorerTxUrl' => ['nullable', 'url:https', 'max:200'],
            'isActive' => ['boolean'],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:1000'],
            'qrImage' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ], [
            'coin.regex' => __('Use the coin\'s ticker, e.g. USDT, BTC or ETH.'),
            'address.regex' => __('The address cannot contain spaces.'),
        ]);

        if ($validated['coin'] === 'BTC' && ! BitcoinAddress::isValid($validated['address'])) {
            $this->addError('address', __('This is not a valid Bitcoin address. Check every character.'));

            return;
        }

        $wallet = DepositWallet::query()->updateOrCreate(['id' => $this->editingId], [
            'coin' => $validated['coin'],
            'network' => $validated['network'],
            'address' => $validated['address'],
            'explorer_tx_url' => $validated['explorerTxUrl'] ?: null,
            'is_active' => $validated['isActive'],
            'sort_order' => $validated['sortOrder'],
        ]);

        if ($this->qrImage || $this->removeQr) {
            if ($wallet->qr_path) {
                Storage::disk('public')->delete($wallet->qr_path);
            }

            $wallet->update(['qr_path' => $this->qrImage?->store('deposit-wallets', 'public')]);
        }

        $this->reset('qrImage', 'removeQr');
        $this->showForm = false;
        unset($this->wallets);
        Flux::toast(variant: 'success', text: __('Wallet saved.'));
    }

    public function toggle(DepositWallet $wallet): void
    {
        $wallet->update(['is_active' => ! $wallet->is_active]);
        unset($this->wallets);
    }

    /**
     * Wallets that already received deposits are kept for the history; turn them off instead.
     */
    public function delete(DepositWallet $wallet): void
    {
        if ($wallet->deposits()->exists()) {
            Flux::toast(variant: 'danger', text: __('This wallet has deposits on record. Turn it off instead so the history stays complete.'));

            return;
        }

        if ($wallet->qr_path) {
            Storage::disk('public')->delete($wallet->qr_path);
        }

        $wallet->delete();
        unset($this->wallets);
        Flux::toast(text: __('Wallet deleted.'));
    }
};
?>

<div class="max-w-4xl space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Deposit wallets') }}</flux:heading>
            <flux:text class="mt-1">{{ __('The company addresses customers can deposit to, in any coin. Deposits are credited in USDT when you approve them; prices are suggested for USDT, USDC, BTC, ETH and SOL.') }}</flux:text>
        </div>
        <flux:button wire:click="create" variant="primary" icon="plus">{{ __('Add wallet') }}</flux:button>
    </div>

    <flux:callout color="amber" icon="exclamation-triangle">
        <flux:callout.text>{{ __('Double-check every address and network before saving. Coins sent to a wrong address or on the wrong network are lost.') }}</flux:callout.text>
    </flux:callout>

    <livewire:pages::admin.bybit-account lazy />

    <div class="space-y-3">
        @forelse ($this->wallets as $wallet)
            <flux:card wire:key="wallet-{{ $wallet->id }}" class="flex flex-wrap items-start justify-between gap-4">
                @if ($wallet->qrUrl())
                    <img src="{{ $wallet->qrUrl() }}" alt="{{ __(':coin QR code', ['coin' => $wallet->coin]) }}" class="size-20 shrink-0 rounded-lg border border-zinc-200 bg-white object-contain p-1 dark:border-white/10">
                @else
                    <span class="flex size-20 shrink-0 items-center justify-center rounded-lg border border-dashed border-zinc-300 text-center text-[11px] text-zinc-400 dark:border-white/15">{{ __('No QR code') }}</span>
                @endif
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:heading>{{ $wallet->coin }}</flux:heading>
                        <flux:badge size="sm">{{ $wallet->network }}</flux:badge>
                        @if ($wallet->is_active)
                            <flux:badge size="sm" color="green">{{ __('Accepting deposits') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">{{ __('Off') }}</flux:badge>
                        @endif
                    </div>
                    <code class="mt-2 block font-mono text-sm break-all text-zinc-700 dark:text-zinc-200">{{ $wallet->address }}</code>
                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-500" data-wallet-totals>
                        <span>{{ __('Real money credited:') }} <x-usdt :micros="(int) $wallet->credited_micros" class="font-semibold text-green-700 dark:text-green-400" /></span>
                        <span>{{ trans_choice(':count deposit|:count deposits', $wallet->deposits_count) }}</span>
                        @if ($wallet->pending_count > 0)
                            <span class="font-medium text-amber-600 dark:text-amber-400">{{ trans_choice(':count waiting for review|:count waiting for review', $wallet->pending_count) }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex shrink-0 gap-2">
                    <flux:button size="sm" wire:click="toggle({{ $wallet->id }})">{{ $wallet->is_active ? __('Turn off') : __('Turn on') }}</flux:button>
                    <flux:button size="sm" wire:click="edit({{ $wallet->id }})" icon="pencil-square" :aria-label="__('Edit')" />
                    <flux:button size="sm" variant="danger" wire:click="delete({{ $wallet->id }})" wire:confirm="{{ __('Delete this wallet?') }}" icon="trash" :aria-label="__('Delete')" />
                </div>
            </flux:card>
        @empty
            <flux:card>
                <flux:text>{{ __('No deposit wallets yet. Customers cannot deposit until you add one.') }}</flux:text>
            </flux:card>
        @endforelse
    </div>

    <flux:pagination :paginator="$this->wallets" />

    <flux:modal wire:model.self="showForm" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit wallet') : __('Add wallet') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="coin" :label="__('Coin')" placeholder="USDT" :description="__('Ticker, e.g. USDT, BTC, ETH')" />
                <flux:input wire:model="network" :label="__('Network')" placeholder="TRC20" :description="__('e.g. TRC20, ERC20, Bitcoin')" />
            </div>
            <flux:input wire:model="address" :label="__('Wallet address')" class="font-mono" autocomplete="off" spellcheck="false" />
            <flux:input wire:model="explorerTxUrl" :label="__('Explorer link for transactions (optional)')" placeholder="https://tronscan.org/#/transaction/"
                :description="__('Used for the Check on blockchain links. The transaction ID is added to the end.')" />
            @php($currentQr = $editingId ? DepositWallet::query()->find($editingId)?->qrUrl() : null)
            <flux:field>
                <flux:label>{{ __('QR code image (optional)') }}</flux:label>
                <div class="flex items-center gap-4">
                    @if ($qrImage?->isPreviewable())
                        <img src="{{ $qrImage->temporaryUrl() }}" alt="" class="size-20 rounded-lg border border-zinc-200 bg-white object-contain p-1">
                    @elseif ($currentQr && ! $removeQr)
                        <img src="{{ $currentQr }}" alt="" class="size-20 rounded-lg border border-zinc-200 bg-white object-contain p-1">
                    @endif
                    <div class="min-w-0 flex-1 space-y-2">
                        <input type="file" wire:model="qrImage" accept="image/png,image/jpeg,image/webp"
                            class="block w-full text-sm text-zinc-600 file:me-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-zinc-800 hover:file:bg-zinc-200 dark:text-zinc-300 dark:file:bg-white/10 dark:file:text-white">
                        @if ($currentQr && ! $qrImage)
                            <flux:checkbox wire:model.live="removeQr" :label="__('Remove the current QR code')" />
                        @endif
                    </div>
                </div>
                <flux:description>{{ __('A PNG, JPG or WebP of the wallet\'s QR code, up to 2 MB. Customers can scan it from their phone.') }}</flux:description>
                <flux:error name="qrImage" />
            </flux:field>
            <flux:input wire:model="sortOrder" type="number" min="0" :label="__('Order')" />
            <flux:switch wire:model="isActive" :label="__('Accept deposits to this wallet')" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
