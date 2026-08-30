<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\MentorSessions\Pages\ListMentorSessions;
use App\Models\MentorSession;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MentorSessionResourceTest extends TestCase
{
    use RefreshDatabase;

    public function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function createSession(): MentorSession
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);

        return MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addDays(3),
        ]);
    }

    public function test_non_admin_cannot_access_the_list(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get('/admin/mentor-sessions')->assertForbidden();
    }

    public function test_admin_can_see_an_existing_session_in_the_list(): void
    {
        $session = $this->createSession();

        $this->actingAs($this->admin());

        Livewire::test(ListMentorSessions::class)
            ->assertCanSeeTableRecords([$session]);
    }

    public function test_admin_can_delete_a_session(): void
    {
        $session = $this->createSession();

        $this->actingAs($this->admin());

        Livewire::test(ListMentorSessions::class)
            ->callTableAction(DeleteAction::class, record: $session);

        $this->assertDatabaseMissing('mentor_sessions', ['id' => $session->id]);
    }
}
