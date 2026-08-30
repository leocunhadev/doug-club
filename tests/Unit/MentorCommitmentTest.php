<?php

namespace Tests\Unit;

use App\Models\MentorCommitment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentorCommitmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_relationship_resolves(): void
    {
        $member = User::factory()->create(['tier' => 'club']);
        $commitment = MentorCommitment::create([
            'member_id' => $member->id,
            'text' => 'Gravar 3 conversas de venda até 09/jul',
        ]);

        $this->assertTrue($commitment->member->is($member));
    }

    public function test_commitment_is_deleted_when_the_member_is_deleted(): void
    {
        $member = User::factory()->create(['tier' => 'club']);
        $commitment = MentorCommitment::create(['member_id' => $member->id, 'text' => 'Algo']);

        $member->delete();

        $this->assertDatabaseMissing('mentor_commitments', ['id' => $commitment->id]);
    }

    public function test_member_id_must_be_unique(): void
    {
        $member = User::factory()->create(['tier' => 'club']);
        MentorCommitment::create(['member_id' => $member->id, 'text' => 'Primeiro']);

        $this->expectException(QueryException::class);

        MentorCommitment::create(['member_id' => $member->id, 'text' => 'Segundo']);
    }
}
