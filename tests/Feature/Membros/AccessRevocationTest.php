<?php

namespace Tests\Feature\Membros;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessRevocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_session_is_logged_out_when_access_is_revoked_mid_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->get('/membros')->assertOk();

        $user->update(['access_revoked_at' => now()]);

        $this->get('/membros')->assertRedirect('/login');

        $this->assertGuest();
    }
}
