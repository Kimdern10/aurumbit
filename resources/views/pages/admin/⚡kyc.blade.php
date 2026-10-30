<?php

use App\Enums\KycStatus;
use App\Models\KycSubmission;
use App\Notifications\AccountActivity;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Verifications')] class extends Component {
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
     * @return LengthAwarePaginator<int, KycSubmission>
     */
    #[Computed]
    public function submissions(): LengthAwarePaginator
    {
        return KycSubmission::query()->with('user:id,name,email')
            ->when(KycStatus::tryFrom($this->status), fn ($query, KycStatus $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(20);
    }

    public function review(int $submissionId, string $decision): void
    {
        $this->reviewingId = $submissionId;
        $this->decision = $decision === 'reject' ? 'reject' : 'approve';
        $this->note = '';
        $this->resetValidation();
        Flux::modal('review-kyc')->show();
    }

    public function confirm(): void
    {
        $this->validate(['note' => [$this->decision === 'reject' ? 'required' : 'nullable', 'string', 'max:500']]);

        $submission = KycSubmission::query()->findOrFail($this->reviewingId);

        if ($submission->status !== KycStatus::Pending) {
            Flux::toast(variant: 'danger', text: __('This check has already been reviewed.'));

            return;
        }

        $submission->forceFill([
            'status' => $this->decision === 'reject' ? KycStatus::Rejected : KycStatus::Approved,
            'admin_note' => $this->note ?: null,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ])->save();

        $submission->user->notify($this->decision === 'reject'
            ? new AccountActivity('Identity check rejected', 'Please send new documents.'.($this->note ? ' Reason: '.$this->note : ''), route('kyc'), 'danger')
            : new AccountActivity('Identity verified', 'Your identity is verified. You can now withdraw.', route('withdraw'), 'success'));

        Flux::modal('review-kyc')->close();
        Flux::toast(variant: 'success', text: $this->decision === 'reject' ? __('Verification rejected.') : __('Identity verified.'));
        unset($this->submissions);
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Identity verifications') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Open each file, compare the name and photo, then approve or reject with a reason.') }}</flux:text>
        </div>
        <flux:select wire:model.live="status" class="sm:max-w-48">
            @foreach (KycStatus::cases() as $case)
                <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
            @endforeach
            <flux:select.option value="all">{{ __('All') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:card class="!p-0 overflow-hidden">
        <flux:table :paginate="$this->submissions" class="[&_td]:px-4! [&_th]:px-4!">
            <flux:table.columns>
                <flux:table.column>{{ __('User') }}</flux:table.column>
                <flux:table.column>{{ __('Name on document') }}</flux:table.column>
                <flux:table.column>{{ __('Files') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->submissions as $submission)
                    <flux:table.row :key="$submission->id">
                        <flux:table.cell>
                            <a href="{{ route('admin.users.show', $submission->user_id) }}" wire:navigate class="font-medium text-zinc-900 hover:underline dark:text-white">{{ $submission->user->name }}</a>
                            <p class="text-xs text-zinc-500">{{ $submission->created_at->format('j M Y, H:i') }}</p>
                        </flux:table.cell>
                        <flux:table.cell>
                            <p>{{ $submission->full_name }}</p>
                            <p class="text-xs text-zinc-500">{{ $submission->documentTypeLabel() }}</p>
                        </flux:table.cell>
                        <flux:table.cell class="space-x-3 whitespace-nowrap">
                            <flux:link :href="route('admin.kyc.file', [$submission, 'document'])" target="_blank">{{ __('Document') }}</flux:link>
                            <flux:link :href="route('admin.kyc.file', [$submission, 'selfie'])" target="_blank">{{ __('Selfie') }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$submission->status->color()">{{ $submission->status->label() }}</flux:badge>
                            @if ($submission->admin_note) <p class="mt-1 max-w-48 truncate text-xs text-zinc-500">{{ $submission->admin_note }}</p> @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($submission->status === KycStatus::Pending)
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" variant="primary" wire:click="review({{ $submission->id }}, 'approve')">{{ __('Approve') }}</flux:button>
                                    <flux:button size="sm" variant="danger" wire:click="review({{ $submission->id }}, 'reject')">{{ __('Reject') }}</flux:button>
                                </div>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="5" class="text-center">{{ __('Nothing here.') }}</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="review-kyc" class="w-full max-w-md">
        <form wire:submit="confirm" class="space-y-4">
            <flux:heading size="lg">{{ $decision === 'reject' ? __('Reject verification') : __('Approve verification') }}</flux:heading>
            <flux:textarea wire:model="note" :label="$decision === 'reject' ? __('Reason (the user will see this)') : __('Note (optional)')" rows="3" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" :variant="$decision === 'reject' ? 'danger' : 'primary'">{{ $decision === 'reject' ? __('Reject') : __('Approve') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
