<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col gap-7">
        <x-auth-header :eyebrow="__('Get started')" :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Name')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Full name')"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <!-- Referral code: filled in from an invite link (?ref=CODE), or typed in -->
            <flux:input
                name="ref"
                :label="__('Referral code (optional)')"
                :value="strtoupper((string) old('ref', request('ref')))"
                type="text"
                autocomplete="off"
                autocapitalize="characters"
                spellcheck="false"
                maxlength="16"
                :placeholder="__('e.g. EMMA2026')"
                :description="__('Invited by someone? Enter their code so they get their bonus.')"
                class="font-mono uppercase"
                data-test="referral-code"
            />

            <flux:field variant="inline">
                <flux:checkbox name="terms" value="1" :checked="(bool) old('terms')" required />
                <flux:label class="text-sm leading-snug">
                    <span>
                        {{ __('I agree to the') }}
                        <a href="{{ route('legal.terms') }}" target="_blank" class="font-medium underline">{{ __('Terms of use') }}</a>
                        {{ __('and') }}
                        <a href="{{ route('legal.privacy') }}" target="_blank" class="font-medium underline">{{ __('Privacy policy') }}</a>.
                    </span>
                </flux:label>
                <flux:error name="terms" />
            </flux:field>

            <flux:button type="submit" variant="primary" icon:trailing="arrow-right" class="mt-1 h-11! w-full rounded-full! text-[0.95rem]!" data-test="register-user-button">
                {{ __('Create account') }}
            </flux:button>
        </form>

        <div class="space-x-1 border-t border-ink-100 pt-6 text-center text-sm text-ink-500 rtl:space-x-reverse dark:border-white/10 dark:text-ink-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
