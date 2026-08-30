<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\BridgeRequests\Pages\ListBridgeRequests;
use App\Models\BridgeRequest;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BridgeRequestResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function bridgeRequest(): BridgeRequest
    {
        $requester = User::factory()->create(['tier' => 'club', 'name' => 'Ana Souza']);
        $target = User::factory()->create(['tier' => 'club', 'name' => 'Beto Lima']);

        return BridgeRequest::create([
            'requester_id' => $requester->id,
            'target_id' => $target->id,
        ]);
    }

    public function test_non_admin_cannot_access_the_list(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get('/admin/bridge-requests')->assertForbidden();
    }

    public function test_admin_can_see_an_existing_request_in_the_list(): void
    {
        $bridgeRequest = $this->bridgeRequest();

        $this->actingAs($this->admin());

        Livewire::test(ListBridgeRequests::class)
            ->assertCanSeeTableRecords([$bridgeRequest])
            ->assertSee('Ana Souza')
            ->assertSee('Beto Lima');
    }

    public function test_admin_can_delete_a_request(): void
    {
        $bridgeRequest = $this->bridgeRequest();

        $this->actingAs($this->admin());

        Livewire::test(ListBridgeRequests::class)
            ->callTableAction(DeleteAction::class, record: $bridgeRequest);

        $this->assertDatabaseMissing('bridge_requests', ['id' => $bridgeRequest->id]);
    }
}
