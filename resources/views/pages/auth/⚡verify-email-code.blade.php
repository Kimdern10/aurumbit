<?php

use App\Support\EmailVerificationCode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

new class extends Component {
    public string $code = '';

    public bool $sent = false;

    /**
     * Users who signed up before codes existed, or whose code expired, get one straight away.
     */
    public function mount(): void
    {
        if (! EmailVerificationCode::isUsable(Auth::user())) {
            $this->resend();
            $this->sent = $this->sent && ! $this->getErrorBag()->has('code');
            $this->resetErrorBag();
        }
    }

    /**
     * Check the code. Wrong codes are counted, so after a few tries a new one is needed.
     */
    public function verify(): void
    {
        $this->code = preg_replace('/\D/', '', $this->code);

        $this->validate(['code' => ['required', 'digits:6']], [
            'code.digits' => __('Enter the 6-digit code from the email.'),
        ]);

        if (! EmailVerificationCode::verify(Auth::user(), $this->code)) {
            $this->reset('code');
            $this->addError('code', EmailVerificationCode::isUsable(Auth::user())
                ? __('That code is not right. Check the email and try again.')
                : __('This code has expired or had too many tries. Send a new code.'));

            return;
        }

        $this->redirectIntended(Auth::user()->is_admin ? route('admin.dashboard') : route('dashboard'), navigate: false);
    }

    /**
     * Email a new code, at most once a minute.
     */
    public function resend(): void
    {
        $key = 'verify-email-code:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $this->addError('code', __('Please wait :seconds seconds before asking for another code.', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }

        RateLimiter::hit($key, 60);
        Auth::user()->sendEmailVerificationNotification();

        $this->reset('code');
        $this->resetErrorBag();
        $this->sent = true;
    }
};
?>

<div class="flex flex-col gap-5">
    @if ($sent)
        <div class="rounded-xl bg-green-50 px-4 py-3 text-sm font-medium text-green-700 dark:bg-green-500/10 dark:text-green-400">
            {{ __('A new code is on its way. It works for :minutes minutes.', ['minutes' => EmailVerificationCode::MINUTES_VALID]) }}
        </div>
    @endif

    <form wire:submit="verify" class="flex flex-col gap-5">
        <flux:input
            wire:model="code"
            :label="__('Verification code')"
            inputmode="numeric"
            autocomplete="one-time-code"
            maxlength="7"
            placeholder="000000"
            autofocus
            class="text-center font-mono text-2xl! tracking-[0.5em]"
            data-test="verification-code"
        />

        <flux:button type="submit" variant="primary" icon:trailing="arrow-right" class="h-11! w-full rounded-full! text-[0.95rem]!">
            {{ __('Verify email') }}
        </flux:button>
    </form>

    <p class="text-center text-sm text-ink-500 dark:text-ink-400">
        {{ __('Didn\'t get it? Check your spam folder or') }}
        <button type="button" wire:click="resend" class="font-medium text-brand-600 underline hover:text-brand-700 dark:text-brand-400">{{ __('send a new code') }}</button>
    </p>
</div>
