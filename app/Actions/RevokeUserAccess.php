<?php

namespace App\Actions;

use App\Models\User;

class RevokeUserAccess
{
    public function handle(string $email): void
    {
        User::where('email', $email)
            ->whereNull('access_revoked_at')
            ->update(['access_revoked_at' => now()]);
    }
}
