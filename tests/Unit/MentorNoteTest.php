<?php

namespace Tests\Unit;

use App\Models\MentorNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentorNoteTest extends TestCase
{
    use RefreshDatabase;

    private function note(array $overrides = []): MentorNote
    {
        $member = User::factory()->create(['tier' => 'club']);
        $mentor = User::factory()->create(['tier' => 'mentor']);

        return MentorNote::create(array_merge([
            'member_id' => $member->id,
            'mentor_id' => $mentor->id,
            'title' => 'A virada do comercial',
            'body' => 'Decidiu tirar o sócio da operação de venda e assumir o discurso.',
        ], $overrides));
    }

    public function test_member_and_mentor_relationships_resolve(): void
    {
        $note = $this->note();

        $this->assertTrue($note->member->is(User::find($note->member_id)));
        $this->assertTrue($note->mentor->is(User::find($note->mentor_id)));
    }

    public function test_note_is_deleted_when_the_member_is_deleted(): void
    {
        $note = $this->note();
        $member = $note->member;

        $member->delete();

        $this->assertDatabaseMissing('mentor_notes', ['id' => $note->id]);
    }

    public function test_note_is_deleted_when_the_mentor_is_deleted(): void
    {
        $note = $this->note();
        $mentor = $note->mentor;

        $mentor->delete();

        $this->assertDatabaseMissing('mentor_notes', ['id' => $note->id]);
    }
}
