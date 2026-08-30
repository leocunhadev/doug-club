<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTierTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_default_to_start_tier(): void
    {
        $user = User::factory()->create();

        $this->assertSame('start', $user->tier);
    }

    public function test_has_club_access_is_true_for_club_and_mentor_tiers(): void
    {
        $this->assertTrue(User::factory()->create(['tier' => 'club'])->hasClubAccess());
        $this->assertTrue(User::factory()->create(['tier' => 'mentor'])->hasClubAccess());
        $this->assertFalse(User::factory()->create(['tier' => 'start'])->hasClubAccess());
    }

    public function test_is_mentor_is_true_only_for_mentor_tier(): void
    {
        $this->assertTrue(User::factory()->create(['tier' => 'mentor'])->isMentor());
        $this->assertFalse(User::factory()->create(['tier' => 'club'])->isMentor());
        $this->assertFalse(User::factory()->create(['tier' => 'start'])->isMentor());
    }

    public function test_is_start_is_true_only_for_start_tier(): void
    {
        $this->assertTrue(User::factory()->create(['tier' => 'start'])->isStart());
        $this->assertFalse(User::factory()->create(['tier' => 'club'])->isStart());
        $this->assertFalse(User::factory()->create(['tier' => 'mentor'])->isStart());
    }

    public function test_initials_are_computed_from_the_users_name(): void
    {
        $user = User::factory()->create(['name' => 'Ana Souza']);

        $this->assertSame('AS', $user->initials);
    }

    public function test_initials_take_at_most_two_letters(): void
    {
        $user = User::factory()->create(['name' => 'Ana Maria Souza Lima']);

        $this->assertSame('AM', $user->initials);
    }
}
