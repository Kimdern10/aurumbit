<x-layouts::auth :title="__('Email verification')">
    <div class="flex flex-col gap-7">
        <x-auth-header :eyebrow="__('One last step')" :title="__('Check your inbox')" :description="__('We emailed a 6-digit code to :email. Enter it below to open your account.', ['email' => auth()->user()->email])" />

        <livewire:pages::auth.verify-email-code />

        <form method="POST" action="{{ route('logout') }}" class="border-t border-ink-100 pt-6 text-center dark:border-white/10">
            @csrf
            <flux:button variant="ghost" type="submit" class="cursor-pointer text-sm" data-test="logout-button">
                {{ __('Log out') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
