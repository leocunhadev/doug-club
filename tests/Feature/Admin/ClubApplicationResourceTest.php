<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ClubApplications\Pages\ListClubApplications;
use App\Models\ClubApplication;
use App\Models\User;
use App\Notifications\ClubApplicationApproved;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ClubApplicationResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function application(): ClubApplication
    {
        $applicant = User::factory()->create([
            'tier' => 'start', 'name' => 'Carla Nunes', 'email' => 'carla@example.com',
        ]);

        return ClubApplication::create(['user_id' => $applicant->id]);
    }

    public function test_non_admin_cannot_access_the_list(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get('/admin/club-applications')->assertForbidden();
    }

    public function test_admin_can_see_an_existing_application_in_the_list(): void
    {
        $application = $this->application();

        $this->actingAs($this->admin());

        Livewire::test(ListClubApplications::class)
            ->assertCanSeeTableRecords([$application])
            ->assertSee('Carla Nunes')
            ->assertSee('carla@example.com');
    }

    public function test_approving_upgrades_the_user_notifies_them_and_removes_the_application(): void
    {
        Notification::fake();

        $application = $this->application();
        $applicant = $application->user;

        $this->actingAs($this->admin());

        Livewire::test(ListClubApplications::class)
            ->callTableAction('approve', record: $application);

        $this->assertSame('club', $applicant->fresh()->tier);
        $this->assertDatabaseMissing('club_applications', ['id' => $application->id]);
        Notification::assertSentTo($applicant, ClubApplicationApproved::class);
    }

    public function test_declining_only_deletes_the_application(): void
    {
        $application = $this->application();
        $applicant = $application->user;

        $this->actingAs($this->admin());

        Livewire::test(ListClubApplications::class)
            ->callTableAction(DeleteAction::class, record: $application);

        $this->assertDatabaseMissing('club_applications', ['id' => $application->id]);
        $this->assertSame('start', $applicant->fresh()->tier);
    }
}
