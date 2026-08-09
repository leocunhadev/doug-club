<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ActivateUserFromPayment
{
    public function handle(string $email, ?string $name): User
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name' => $name ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Str::password(32),
                'email_verified_at' => now(),
            ]);

            Password::sendResetLink(['email' => $email]);

            return $user;
        }

        if ($user->access_revoked_at !== null) {
            $user->update(['access_revoked_at' => null]);
        }

        return $user;
    }
}
