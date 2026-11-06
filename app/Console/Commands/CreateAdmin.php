<?php

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Enums\AdminRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-admin {--name= : The admin\'s name} {--email= : The email used to log in} {--password= : The password (you will be asked if left out)} {--role=owner : owner (full access) or support (cannot move money or change settings)}')]
#[Description('Create an admin login, or make an existing user an admin')]
class CreateAdmin extends Command
{
    use PasswordValidationRules;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $role = AdminRole::tryFrom((string) $this->option('role'));

        if ($role === null) {
            $this->components->error('The role must be "owner" or "support".');

            return self::FAILURE;
        }

        $email = $this->option('email') ?: text('Email address to log in with', required: true);
        $user = User::query()->where('email', strtolower($email))->first();

        $name = $this->option('name') ?: ($user->name ?? text('Your name', default: 'Admin', required: true));
        $password = $this->option('password') ?: password('Password (at least 8 characters)', required: true);

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $password],
            ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'], 'password' => $this->passwordRules()],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user ??= new User;

        $user->forceFill([
            'name' => $name,
            'email' => strtolower($email),
            'password' => $password,
            'is_admin' => true,
            'admin_role' => $role,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $this->components->info("{$user->email} can now log in at ".route('login'));

        return self::SUCCESS;
    }
}
