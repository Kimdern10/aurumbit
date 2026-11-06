<?php

use App\Models\Announcement;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Announcements')] class extends Component {
    use WithPagination;

    public ?int $editingId = null;

    public bool $showForm = false;

    public string $title = '';

    public string $body = '';

    public string $level = 'info';

    public bool $isPublished = true;

    /**
     * @return LengthAwarePaginator<int, Announcement>
     */
    #[Computed]
    public function announcements(): LengthAwarePaginator
    {
        return Announcement::query()->latest()->paginate(10);
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'title', 'body', 'level', 'isPublished');
        $this->showForm = true;
    }

    public function edit(Announcement $announcement): void
    {
        $this->resetValidation();
        $this->editingId = $announcement->id;
        $this->title = $announcement->title;
        $this->body = $announcement->body;
        $this->level = $announcement->level;
        $this->isPublished = $announcement->is_published;
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
            'level' => ['required', Rule::in(array_keys(Announcement::LEVELS))],
            'isPublished' => ['boolean'],
        ]);

        Announcement::query()->updateOrCreate(['id' => $this->editingId], [
            'title' => $validated['title'],
            'body' => $validated['body'],
            'level' => $validated['level'],
            'is_published' => $validated['isPublished'],
        ]);

        $this->showForm = false;
        unset($this->announcements);
        Flux::toast(variant: 'success', text: __('Announcement saved.'));
    }

    public function delete(Announcement $announcement): void
    {
        $announcement->delete();
        unset($this->announcements);
        Flux::toast(text: __('Announcement deleted.'));
    }
};
?>

<div class="max-w-4xl space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Announcements') }}</flux:heading>
            <flux:text class="mt-1">{{ __('The three newest published announcements show on every user dashboard.') }}</flux:text>
        </div>
        <flux:button wire:click="create" variant="primary" icon="plus">{{ __('New announcement') }}</flux:button>
    </div>

    <div class="space-y-3">
        @forelse ($this->announcements as $announcement)
            <flux:card wire:key="ann-{{ $announcement->id }}" class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:heading>{{ $announcement->title }}</flux:heading>
                        <flux:badge size="sm" :color="$announcement->calloutColor()">{{ Announcement::LEVELS[$announcement->level] ?? $announcement->level }}</flux:badge>
                        @unless ($announcement->is_published) <flux:badge size="sm">{{ __('Hidden') }}</flux:badge> @endunless
                    </div>
                    <flux:text class="mt-1 text-sm">{{ $announcement->body }}</flux:text>
                    <flux:text class="mt-1 text-xs">{{ $announcement->created_at->format('M j, Y') }}</flux:text>
                </div>
                <div class="flex shrink-0 gap-2">
                    <flux:button size="sm" wire:click="edit({{ $announcement->id }})" icon="pencil-square" :aria-label="__('Edit')" />
                    <flux:button size="sm" variant="danger" wire:click="delete({{ $announcement->id }})" wire:confirm="{{ __('Delete this announcement?') }}" icon="trash" :aria-label="__('Delete')" />
                </div>
            </flux:card>
        @empty
            <flux:text>{{ __('No announcements yet.') }}</flux:text>
        @endforelse
    </div>

    <flux:pagination :paginator="$this->announcements" />

    <flux:modal wire:model.self="showForm" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit announcement') : __('New announcement') }}</flux:heading>
            <flux:input wire:model="title" :label="__('Title')" />
            <flux:textarea wire:model="body" :label="__('Message')" rows="4" />
            <flux:select wire:model="level" :label="__('Type')">
                @foreach (Announcement::LEVELS as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:switch wire:model="isPublished" :label="__('Show on dashboards')" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
