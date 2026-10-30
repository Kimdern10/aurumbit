<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
            'ref' => ['nullable', 'string', 'max:16'],
        ], [
            'terms.accepted' => 'Please confirm you have read the terms and the risk disclosure.',
        ])->validate();

        $user = new User([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        if (filled($input['ref'] ?? null)) {
            $user->referred_by_id = User::query()->where('referral_code', strtoupper($input['ref']))->value('id');
        }

        $user->save();

        return $user;
    }
}
