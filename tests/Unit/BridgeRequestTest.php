<?php

namespace Tests\Unit;

use App\Models\BridgeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BridgeRequestTest extends TestCase
{
    use RefreshDatabase;

    private function bridgeRequest(): BridgeRequest
    {
        $requester = User::factory()->create(['tier' => 'club']);
        $target = User::factory()->create(['tier' => 'club']);

        return BridgeRequest::create([
            'requester_id' => $requester->id,
            'target_id' => $target->id,
        ]);
    }

    public function test_requester_and_target_relationships_resolve(): void
    {
        $bridgeRequest = $this->bridgeRequest();

        $this->assertTrue($bridgeRequest->requester->is(User::find($bridgeRequest->requester_id)));
        $this->assertTrue($bridgeRequest->target->is(User::find($bridgeRequest->target_id)));
    }

    public function test_request_is_deleted_when_the_requester_is_deleted(): void
    {
        $bridgeRequest = $this->bridgeRequest();
        $requester = $bridgeRequest->requester;

        $requester->delete();

        $this->assertDatabaseMissing('bridge_requests', ['id' => $bridgeRequest->id]);
    }

    public function test_request_is_deleted_when_the_target_is_deleted(): void
    {
        $bridgeRequest = $this->bridgeRequest();
        $target = $bridgeRequest->target;

        $target->delete();

        $this->assertDatabaseMissing('bridge_requests', ['id' => $bridgeRequest->id]);
    }
}
