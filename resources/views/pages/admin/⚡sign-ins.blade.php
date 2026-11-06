<?php

use App\Models\AdminSignIn;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Admin sign-ins')] class extends Component {
    use WithPagination;

    /**
     * Only owners may open this page.
     */
    public function boot(): void
    {
        Gate::authorize('manage-money');
    }

    /**
     * @return LengthAwarePaginator<int, AdminSignIn>
     */
    #[Computed]
    public function signIns(): LengthAwarePaginator
    {
        return AdminSignIn::query()->with('user:id,name,email,is_admin,admin_role')->latest('id')->paginate(25);
    }

    /**
     * Everyone who can open the admin panel, with their role.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function admins(): Collection
    {
        return User::query()->where('is_admin', true)->orderBy('name')->get(['id', 'name', 'email', 'is_admin', 'admin_role']);
    }
};
?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Admin sign-ins') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Every time someone signs in to the admin panel. If you see a sign-in you do not recognise, change that admin\'s password straight away.') }}</flux:text>
    </div>

    <flux:card class="space-y-3">
        <flux:heading>{{ __('Admins') }}</flux:heading>
        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach ($this->admins as $admin)
                <div class="flex flex-wrap items-center justify-between gap-2 py-2" wire:key="admin-{{ $admin->id }}">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ $admin->name }}</p>
                        <p class="truncate text-xs text-zinc-500">{{ $admin->email }}</p>
                    </div>
                    <flux:badge size="sm" :color="$admin->isOwner() ? 'amber' : 'zinc'">{{ $admin->admin_role?->label() ?? __('No role') }}</flux:badge>
                </div>
            @endforeach
        </div>
        <flux:text class="text-xs">{{ __('Add an admin or change a role from the server: php artisan app:create-admin --role=support (or --role=owner).') }}</flux:text>
    </flux:card>

    <flux:card class="!p-0 overflow-hidden">
        <flux:table :paginate="$this->signIns" class="[&_td]:px-4! [&_th]:px-4!">
            <flux:table.columns>
                <flux:table.column>{{ __('When') }}</flux:table.column>
                <flux:table.column>{{ __('Admin') }}</flux:table.column>
                <flux:table.column>{{ __('IP address') }}</flux:table.column>
                <flux:table.column>{{ __('Device') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->signIns as $signIn)
                    <flux:table.row :key="$signIn->id">
                        <flux:table.cell class="whitespace-nowrap">{{ $signIn->created_at->format('M j, Y g:i A') }}</flux:table.cell>
                        <flux:table.cell>
                            <p class="font-medium">{{ $signIn->user->name }}</p>
                            <p class="text-xs text-zinc-500">{{ $signIn->user->admin_role?->label() }}</p>
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $signIn->ip_address ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="text-xs" title="{{ $signIn->user_agent }}">{{ $signIn->device() }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="4" class="text-center">{{ __('No admin sign-ins yet.') }}</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
