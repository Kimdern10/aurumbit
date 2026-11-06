<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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
        $input['ref'] = strtoupper(trim((string) ($input['ref'] ?? '')));

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
            'ref' => ['nullable', 'string', 'max:16', Rule::exists('users', 'referral_code')],
        ], [
            'terms.accepted' => 'Please agree to the terms of use and privacy policy.',
            'ref.exists' => 'We could not find that referral code. Check it with the person who invited you, or leave it empty.',
        ])->validate();

        $user = new User([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        if ($input['ref'] !== '') {
            $user->referred_by_id = User::query()->where('referral_code', $input['ref'])->value('id');
        }

        $user->save();

        return $user;
    }
}
