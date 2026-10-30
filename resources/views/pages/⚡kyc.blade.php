<?php

use App\Enums\KycStatus;
use App\Models\KycSubmission;
use Flux\Flux;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Verification')] class extends Component {
    use WithFileUploads;

    public string $fullName = '';

    public string $documentType = 'national_id';

    /** @var UploadedFile|null */
    public $document = null;

    /** @var UploadedFile|null */
    public $selfie = null;

    public function mount(): void
    {
        $this->fullName = Auth::user()->name;
    }

    #[Computed]
    public function latest(): ?KycSubmission
    {
        return Auth::user()->latestKyc;
    }

    #[Computed]
    public function canSubmit(): bool
    {
        return $this->latest === null || $this->latest->status === KycStatus::Rejected;
    }

    public function save(): void
    {
        abort_unless($this->canSubmit, 403);

        $this->validate([
            'fullName' => ['required', 'string', 'max:255'],
            'documentType' => ['required', Rule::in(array_keys(KycSubmission::DOCUMENT_TYPES))],
            'document' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'selfie' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = Auth::user();
        $folder = 'kyc/'.$user->id;

        $user->kycSubmissions()->create([
            'full_name' => $this->fullName,
            'document_type' => $this->documentType,
            'document_path' => $this->document->store($folder, 'local'),
            'selfie_path' => $this->selfie->store($folder, 'local'),
        ]);

        $user->unsetRelation('latestKyc');
        unset($this->latest, $this->canSubmit);
        $this->reset('document', 'selfie');

        Flux::toast(variant: 'success', text: __('Documents sent. We will review them soon.'));
    }
};
?>

<div class="max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Identity verification') }}</flux:heading>
        <flux:text class="mt-1">{{ __('We check identities to protect users and prevent fraud. Your files are stored privately and only seen by an admin.') }}</flux:text>
    </div>

    @if ($this->latest)
        <flux:card class="flex items-start gap-4">
            <flux:icon :name="match ($this->latest->status) { KycStatus::Approved => 'check-badge', KycStatus::Rejected => 'x-circle', default => 'clock' }"
                @class(['size-8 shrink-0', 'text-green-600' => $this->latest->status === KycStatus::Approved, 'text-red-600' => $this->latest->status === KycStatus::Rejected, 'text-amber-500' => $this->latest->status === KycStatus::Pending]) />
            <div>
                <flux:heading>{{ $this->latest->status->label() }}</flux:heading>
                <flux:text class="mt-1 text-sm">
                    {{ __(':type sent on :date.', ['type' => $this->latest->documentTypeLabel(), 'date' => $this->latest->created_at->format('j M Y')]) }}
                    @if ($this->latest->status === KycStatus::Pending)
                        {{ __('An admin will review it soon.') }}
                    @elseif ($this->latest->status === KycStatus::Rejected)
                        {{ __('Please send new documents below.') }}
                    @endif
                </flux:text>
                @if ($this->latest->admin_note)
                    <flux:text class="mt-2 text-sm"><strong>{{ __('Note from the reviewer:') }}</strong> {{ $this->latest->admin_note }}</flux:text>
                @endif
            </div>
        </flux:card>
    @endif

    @if ($this->canSubmit)
        <flux:card>
            <form wire:submit="save" class="space-y-5">
                <flux:input wire:model="fullName" :label="__('Full legal name')" :description="__('As it appears on your document')" />

                <flux:select wire:model="documentType" :label="__('Document type')">
                    @foreach (KycSubmission::DOCUMENT_TYPES as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:field>
                    <flux:label>{{ __('Photo or scan of the document') }}</flux:label>
                    <flux:description>{{ __('JPG, PNG, WebP or PDF, up to 5 MB. All four corners visible.') }}</flux:description>
                    <input type="file" wire:model="document" accept=".jpg,.jpeg,.png,.webp,.pdf" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:font-medium dark:file:bg-white/10 dark:file:text-white" />
                    <div wire:loading wire:target="document" class="text-xs text-zinc-500">{{ __('Uploading…') }}</div>
                    <flux:error name="document" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Selfie holding the document') }}</flux:label>
                    <flux:description>{{ __('JPG, PNG or WebP, up to 5 MB. Your face and the document must be clear.') }}</flux:description>
                    <input type="file" wire:model="selfie" accept="image/*" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:font-medium dark:file:bg-white/10 dark:file:text-white" />
                    <div wire:loading wire:target="selfie" class="text-xs text-zinc-500">{{ __('Uploading…') }}</div>
                    <flux:error name="selfie" />
                </flux:field>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="document,selfie,save">{{ __('Send for review') }}</flux:button>
            </form>
        </flux:card>
    @endif
</div>
