<?php

use App\Enums\RequestStatus;
use App\Exceptions\WalletException;
use App\Models\Deposit;
use App\Services\WalletService;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
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
        return Deposit::query()->with('user:id,name,email')
            ->when(RequestStatus::tryFrom($this->status), fn ($query, RequestStatus $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(20);
    }

    public function approve(int $depositId, WalletService $wallet): void
    {
        try {
            $wallet->approveDeposit(Deposit::query()->findOrFail($depositId), Auth::user());
            Flux::toast(variant: 'success', text: __('Deposit credited.'));
        } catch (WalletException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }

        unset($this->deposits);
    }

    public function startReject(int $depositId): void
    {
        $this->rejectingId = $depositId;
        $this->note = '';
        Flux::modal('reject-deposit')->show();
    }

    public function reject(WalletService $wallet): void
    {
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

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Deposits') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Open each transaction on the blockchain and check the amount reached our address with enough confirmations before you approve.') }}</flux:text>
        </div>
        <flux:select wire:model.live="status" class="sm:max-w-48">
            <flux:select.option value="pending">{{ __('Pending') }}</flux:select.option>
            <flux:select.option value="approved">{{ __('Approved') }}</flux:select.option>
            <flux:select.option value="rejected">{{ __('Rejected') }}</flux:select.option>
            <flux:select.option value="all">{{ __('All') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:card class="!p-0 overflow-hidden">
        <flux:table :paginate="$this->deposits" class="[&_td]:px-4! [&_th]:px-4!">
            <flux:table.columns>
                <flux:table.column>{{ __('User') }}</flux:table.column>
                <flux:table.column>{{ __('Amount') }}</flux:table.column>
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
                        <flux:table.cell><x-btc :sats="$deposit->amount_sats" class="font-medium" /></flux:table.cell>
                        <flux:table.cell class="max-w-40 font-mono text-xs">
                            @if ($deposit->reference)
                                <a href="{{ \App\Models\Setting::get('explorer_tx_url').$deposit->reference }}" target="_blank" rel="noopener" class="block truncate underline" title="{{ $deposit->reference }}">{{ __('Check on blockchain') }}</a>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ $deposit->created_at->format('j M, H:i') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$deposit->status->color()">{{ $deposit->status->label() }}</flux:badge>
                            @if ($deposit->admin_note) <p class="mt-1 max-w-48 truncate text-xs text-zinc-500">{{ $deposit->admin_note }}</p> @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($deposit->status === RequestStatus::Pending)
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" variant="primary" wire:click="approve({{ $deposit->id }})" wire:confirm="{{ __('Have you checked this transaction on the blockchain? Credit it to the user?') }}">{{ __('Approve') }}</flux:button>
                                    <flux:button size="sm" variant="danger" wire:click="startReject({{ $deposit->id }})">{{ __('Reject') }}</flux:button>
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
