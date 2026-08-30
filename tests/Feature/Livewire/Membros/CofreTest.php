<?php

namespace Tests\Feature\Livewire\Membros;

use App\Livewire\Membros\Cofre;
use App\Models\User;
use App\Models\VaultDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CofreTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/membros/cofre')->assertRedirect('/login');
    }

    public function test_member_sees_only_their_own_documents(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);
        $otherMember = User::factory()->create(['tier' => 'club']);

        VaultDocument::create([
            'member_id' => $member->id, 'mentor_id' => $mentor->id,
            'title' => 'Documento do membro', 'file_url' => 'https://example.com/a.pdf',
        ]);
        VaultDocument::create([
            'member_id' => $otherMember->id, 'mentor_id' => $mentor->id,
            'title' => 'Documento de outro membro', 'file_url' => 'https://example.com/b.pdf',
        ]);

        $this->actingAs($member);

        Livewire::test(Cofre::class)
            ->assertSee('Documento do membro')
            ->assertDontSee('Documento de outro membro');
    }

    public function test_novo_badge_shown_only_for_unopened_documents(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);

        VaultDocument::create([
            'member_id' => $member->id, 'mentor_id' => $mentor->id,
            'title' => 'Não visto ainda', 'file_url' => 'https://example.com/a.pdf',
        ]);
        VaultDocument::create([
            'member_id' => $member->id, 'mentor_id' => $mentor->id,
            'title' => 'Já visto', 'file_url' => 'https://example.com/b.pdf', 'opened_at' => now(),
        ]);

        $this->actingAs($member);

        $html = Livewire::test(Cofre::class)->html();

        $this->assertSame(1, substr_count($html, 'Novo'));
    }

    public function test_empty_state_shown_with_no_documents(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Cofre::class)->assertSee('Nenhum documento no seu cofre ainda.');
    }
}
