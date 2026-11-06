<?php

use App\Support\DatabaseBackup;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Backups')] class extends Component {
    /**
     * Only owners may open this page or download a backup.
     */
    public function boot(): void
    {
        Gate::authorize('manage-money');
    }

    /**
     * @return list<array{path: string, name: string, size: int, created_at: Carbon}>
     */
    #[Computed]
    public function backups(): array
    {
        return DatabaseBackup::list();
    }

    public function backUpNow(): void
    {
        DatabaseBackup::create();
        DatabaseBackup::prune((int) config('services.backups.keep', 14));
        unset($this->backups);

        Flux::toast(variant: 'success', text: __('Backup made.'));
    }

    public function download(string $name): StreamedResponse
    {
        $backup = collect($this->backups)->firstWhere('name', $name);
        abort_if($backup === null, 404);

        return DatabaseBackup::disk()->download($backup['path'], $backup['name']);
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Backups') }}</flux:heading>
            <flux:text class="mt-1">{{ __('A copy of the database is made every night at 2:00 and the newest :keep are kept. Now and then, download one and store it somewhere safe, away from this server.', ['keep' => config('services.backups.keep', 14)]) }}</flux:text>
        </div>
        <flux:button variant="primary" icon="arrow-path" wire:click="backUpNow">{{ __('Back up now') }}</flux:button>
    </div>

    <flux:card class="!p-0 overflow-hidden">
        <flux:table class="[&_td]:px-4! [&_th]:px-4!">
            <flux:table.columns>
                <flux:table.column>{{ __('Made') }}</flux:table.column>
                <flux:table.column>{{ __('File') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Size') }}</flux:table.column>
                <flux:table.column align="end"></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->backups as $backup)
                    <flux:table.row :key="$backup['name']">
                        <flux:table.cell class="whitespace-nowrap">{{ $backup['created_at']->format('M j, Y g:i A') }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $backup['name'] }}</flux:table.cell>
                        <flux:table.cell align="end">{{ Number::fileSize($backup['size'], 1) }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" icon="arrow-down-tray" wire:click="download('{{ $backup['name'] }}')">{{ __('Download') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="4" class="text-center">{{ __('No backups yet. Press "Back up now" to make the first one.') }}</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
