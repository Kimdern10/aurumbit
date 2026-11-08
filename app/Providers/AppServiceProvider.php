<?php

namespace App\Providers;

use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::define('manage-money', fn (User $user): bool => $user->isOwner());

        $this->configurePasswordResetEmail();
    }

    /**
     * Word the password reset email like the rest of the site's emails.
     */
    protected function configurePasswordResetEmail(): void
    {
        ResetPassword::toMailUsing(function (User $user, string $token): MailMessage {
            $siteName = Setting::get('site_name');
            $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

            return (new MailMessage)
                ->subject(__('Reset your :site password', ['site' => $siteName]))
                ->greeting(__('Reset your password'))
                ->line(__('We received a request to reset the password for your :site account (:email).', ['site' => $siteName, 'email' => $user->email]))
                ->action(__('Choose a new password'), route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()]))
                ->line(__('This link works for :minutes minutes. For your safety, withdrawals are paused for 24 hours after a password change.', ['minutes' => $minutes]))
                ->line(__('If you did not ask for this, ignore this email. Your password stays the same.'));
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
