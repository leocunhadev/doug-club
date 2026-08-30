<?php

namespace Tests\Unit;

use App\Models\ClubApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relationship_resolves(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $application = ClubApplication::create(['user_id' => $user->id]);

        $this->assertTrue($application->user->is($user));
    }

    public function test_application_is_deleted_when_the_user_is_deleted(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $application = ClubApplication::create(['user_id' => $user->id]);

        $user->delete();

        $this->assertDatabaseMissing('club_applications', ['id' => $application->id]);
    }
}
