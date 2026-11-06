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
        $this->decision = in_array($decision, ['reject', 'revoke'], true) ? $decision : 'approve';
        $this->note = '';
        $this->resetValidation();
        Flux::modal('review-kyc')->show();
    }

    public function confirm(): void
    {
        $this->validate(['note' => [in_array($this->decision, ['reject', 'revoke'], true) ? 'required' : 'nullable', 'string', 'max:500']]);

        $submission = KycSubmission::query()->findOrFail($this->reviewingId);

        if ($this->decision === 'revoke') {
            $this->revoke($submission);

            return;
        }

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

    /**
     * Cancel an approved verification, e.g. when something looks wrong later.
     * The user is no longer verified (so cannot withdraw when that is required)
     * and is asked to send new documents.
     */
    private function revoke(KycSubmission $submission): void
    {
        if ($submission->status !== KycStatus::Approved) {
            Flux::toast(variant: 'danger', text: __('Only an approved verification can be cancelled.'));

            return;
        }

        $submission->forceFill([
            'status' => KycStatus::Revoked,
            'admin_note' => $this->note,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ])->save();

        $submission->user->notify(new AccountActivity('Identity verification cancelled', 'Your identity verification was cancelled. Reason: '.$this->note.' Please send new documents to verify again.', route('kyc'), 'danger'));

        Flux::modal('review-kyc')->close();
        Flux::toast(variant: 'danger', text: __('Verification cancelled. The user must verify again.'));
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
                            <p class="text-xs text-zinc-500">{{ $submission->created_at->format('M j, Y g:i A') }}</p>
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
                            @if ($submission->reviewed_at) <p class="mt-1 text-xs text-zinc-500">{{ __('Reviewed :date', ['date' => $submission->reviewed_at->format('M j, Y')]) }}</p> @endif
                            @if ($submission->admin_note) <p class="mt-1 max-w-48 truncate text-xs text-zinc-500" title="{{ $submission->admin_note }}">{{ $submission->admin_note }}</p> @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($submission->status === KycStatus::Pending)
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" variant="primary" wire:click="review({{ $submission->id }}, 'approve')">{{ __('Approve') }}</flux:button>
                                    <flux:button size="sm" variant="danger" wire:click="review({{ $submission->id }}, 'reject')">{{ __('Reject') }}</flux:button>
                                </div>
                            @elseif ($submission->status === KycStatus::Approved)
                                <flux:button size="sm" variant="danger" icon="no-symbol" wire:click="review({{ $submission->id }}, 'revoke')">{{ __('Cancel verification') }}</flux:button>
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
            <flux:heading size="lg">{{ match ($decision) { 'reject' => __('Reject verification'), 'revoke' => __('Cancel this verification'), default => __('Approve verification') } }}</flux:heading>
            @if ($decision === 'revoke')
                <flux:text class="text-sm">{{ __('The user will no longer be verified and must send new documents. If withdrawals need a verified identity, they cannot withdraw until they are approved again.') }}</flux:text>
            @endif
            <flux:textarea wire:model="note" :label="$decision === 'approve' ? __('Note (optional)') : __('Reason (the user will see this)')" rows="3" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Keep as is') }}</flux:button></flux:modal.close>
                <flux:button type="submit" :variant="$decision === 'approve' ? 'primary' : 'danger'">{{ match ($decision) { 'reject' => __('Reject'), 'revoke' => __('Cancel verification'), default => __('Approve') } }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
