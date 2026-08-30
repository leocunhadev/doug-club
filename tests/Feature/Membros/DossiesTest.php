<?php

namespace Tests\Feature\Membros;

use App\Livewire\Membros\Dossies;
use App\Models\MentorCommitment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DossiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/membros/mentor/dossies')->assertRedirect(route('login'));
    }

    public function test_club_tier_member_is_denied(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $this->get('/membros/mentor/dossies')->assertRedirect(route('dashboard'));
    }

    public function test_page_lists_all_club_members(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        User::factory()->create(['tier' => 'club', 'name' => 'Ricardo Mendes']);
        User::factory()->create(['tier' => 'club', 'name' => 'Marina Prado']);

        Livewire::test(Dossies::class)
            ->assertSee('Ricardo Mendes')
            ->assertSee('Marina Prado');
    }

    public function test_first_member_is_selected_by_default(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        $member = User::factory()->create(['tier' => 'club', 'name' => 'Ricardo Mendes']);

        Livewire::test(Dossies::class)
            ->assertSet('selectedMemberId', $member->id);
    }

    public function test_shows_empty_state_with_no_club_members(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));

        Livewire::test(Dossies::class)
            ->assertSee('Nenhum mentorado ainda.');
    }

    public function test_select_member_changes_the_displayed_member(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        User::factory()->create(['tier' => 'club', 'name' => 'Ricardo Mendes']);
        $second = User::factory()->create(['tier' => 'club', 'name' => 'Marina Prado']);

        Livewire::test(Dossies::class)
            ->call('selectMember', $second->id)
            ->assertSet('selectedMemberId', $second->id);
    }

    public function test_select_member_with_a_non_club_id_does_not_change_the_selection(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        $member = User::factory()->create(['tier' => 'club']);
        $startUser = User::factory()->create(['tier' => 'start']);

        Livewire::test(Dossies::class)
            ->call('selectMember', $startUser->id)
            ->assertSet('selectedMemberId', $member->id);
    }

    public function test_add_note_creates_a_mentor_note_authored_by_the_logged_in_mentor(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $this->actingAs($mentor);
        $member = User::factory()->create(['tier' => 'club']);

        Livewire::test(Dossies::class)
            ->set('noteTitle', 'A virada do comercial')
            ->set('noteBody', 'Decidiu tirar o sócio da operação de venda.')
            ->call('addNote');

        $this->assertDatabaseHas('mentor_notes', [
            'member_id' => $member->id,
            'mentor_id' => $mentor->id,
            'title' => 'A virada do comercial',
        ]);
    }

    public function test_add_note_requires_title_and_body(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        User::factory()->create(['tier' => 'club']);

        Livewire::test(Dossies::class)
            ->set('noteTitle', '')
            ->set('noteBody', '')
            ->call('addNote')
            ->assertHasErrors(['noteTitle', 'noteBody']);
    }

    public function test_save_commitment_creates_the_record_on_first_save(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        $member = User::factory()->create(['tier' => 'club']);

        Livewire::test(Dossies::class)
            ->set('commitmentInput', 'Gravar 3 conversas de venda até 09/jul')
            ->call('saveCommitment');

        $this->assertDatabaseHas('mentor_commitments', [
            'member_id' => $member->id,
            'text' => 'Gravar 3 conversas de venda até 09/jul',
        ]);
    }

    public function test_save_commitment_updates_instead_of_duplicating(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        $member = User::factory()->create(['tier' => 'club']);
        MentorCommitment::create(['member_id' => $member->id, 'text' => 'Primeiro compromisso']);

        Livewire::test(Dossies::class)
            ->set('commitmentInput', 'Compromisso atualizado')
            ->call('saveCommitment');

        $this->assertSame(1, MentorCommitment::where('member_id', $member->id)->count());
        $this->assertDatabaseHas('mentor_commitments', [
            'member_id' => $member->id,
            'text' => 'Compromisso atualizado',
        ]);
    }

    public function test_save_commitment_with_empty_text_stores_null(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        $member = User::factory()->create(['tier' => 'club']);
        MentorCommitment::create(['member_id' => $member->id, 'text' => 'Algo']);

        Livewire::test(Dossies::class)
            ->set('commitmentInput', '   ')
            ->call('saveCommitment');

        $this->assertDatabaseHas('mentor_commitments', [
            'member_id' => $member->id,
            'text' => null,
        ]);
    }

    public function test_selected_member_id_cannot_be_set_directly_by_a_client_payload(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        User::factory()->create(['tier' => 'club']);
        $startUser = User::factory()->create(['tier' => 'start']);

        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

        Livewire::test(Dossies::class)->set('selectedMemberId', $startUser->id);
    }

    public function test_page_does_not_crash_when_the_selected_member_is_deleted_mid_session(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        $first = User::factory()->create(['tier' => 'club']);
        $second = User::factory()->create(['tier' => 'club']);

        $component = Livewire::test(Dossies::class)
            ->call('selectMember', $second->id)
            ->assertSet('selectedMemberId', $second->id);

        $second->delete();

        $component->call('saveCommitment')->assertOk();
    }
}
