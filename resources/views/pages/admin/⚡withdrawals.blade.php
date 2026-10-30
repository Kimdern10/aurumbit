<?php

use App\Enums\RequestStatus;
use App\Exceptions\WalletException;
use App\Models\Withdrawal;
use App\Services\WalletService;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
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

    public function review(int $withdrawalId, string $decision): void
    {
        $this->reviewingId = $withdrawalId;
        $this->decision = $decision === 'reject' ? 'reject' : 'approve';
        $this->note = '';
        $this->resetValidation();
        Flux::modal('review-withdrawal')->show();
    }

    public function confirm(WalletService $wallet): void
    {
        $this->validate(['note' => [$this->decision === 'reject' ? 'required' : 'nullable', 'string', 'max:500']]);

        $withdrawal = Withdrawal::query()->findOrFail($this->reviewingId);

        try {
            if ($this->decision === 'reject') {
                $wallet->rejectWithdrawal($withdrawal, Auth::user(), $this->note);
                Flux::toast(text: __('Withdrawal rejected and the amount returned.'));
            } else {
                $wallet->approveWithdrawal($withdrawal, Auth::user(), $this->note ?: null);
                Flux::toast(variant: 'success', text: __('Withdrawal marked as paid (demo, nothing sent).'));
            }
        } catch (WalletException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }

        Flux::modal('review-withdrawal')->close();
        unset($this->withdrawals);
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Withdrawals') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Demo mode: approving marks the request as paid. No coins are sent.') }}</flux:text>
        </div>
        <flux:select wire:model.live="status" class="sm:max-w-48">
            <flux:select.option value="pending">{{ __('Pending') }}</flux:select.option>
            <flux:select.option value="approved">{{ __('Approved') }}</flux:select.option>
            <flux:select.option value="rejected">{{ __('Rejected') }}</flux:select.option>
            <flux:select.option value="all">{{ __('All') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:card class="!p-0 overflow-hidden">
        <flux:table :paginate="$this->withdrawals" class="[&_td]:px-4 [&_th]:px-4">
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
                        <flux:table.cell><x-btc :sats="$withdrawal->amount_sats" class="font-medium" /></flux:table.cell>
                        <flux:table.cell class="max-w-56">
                            <span class="block truncate font-mono text-xs" title="{{ $withdrawal->btc_address }}">{{ $withdrawal->btc_address }}</span>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ $withdrawal->created_at->format('j M, H:i') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$withdrawal->status->color()">{{ $withdrawal->status->label() }}</flux:badge>
                            @if ($withdrawal->admin_note) <p class="mt-1 max-w-48 truncate text-xs text-zinc-500">{{ $withdrawal->admin_note }}</p> @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($withdrawal->status === RequestStatus::Pending)
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
                <flux:text class="text-sm">{{ __('Demo mode: the request is marked as paid. Nothing is sent on the blockchain.') }}</flux:text>
            @endif
            <flux:textarea wire:model="note" :label="$decision === 'reject' ? __('Reason (the user will see this)') : __('Note (optional)')" rows="3" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" :variant="$decision === 'reject' ? 'danger' : 'primary'">{{ $decision === 'reject' ? __('Reject') : __('Approve') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
