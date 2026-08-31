<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\VaultDocuments\Pages\CreateVaultDocument;
use App\Filament\Resources\VaultDocuments\Pages\EditVaultDocument;
use App\Filament\Resources\VaultDocuments\Pages\ListVaultDocuments;
use App\Models\User;
use App\Models\VaultDocument;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class VaultDocumentResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_non_admin_cannot_access_the_list(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get('/admin/vault-documents')->assertForbidden();
    }

    public function test_admin_can_see_an_existing_document_in_the_list(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);
        $document = VaultDocument::create([
            'member_id' => $member->id, 'mentor_id' => $mentor->id,
            'title' => 'Insights da Sessão 4', 'file_url' => 'https://example.com/a.pdf',
        ]);

        $this->actingAs($this->admin());

        Livewire::test(ListVaultDocuments::class)
            ->assertCanSeeTableRecords([$document]);
    }

    public function test_list_shows_upload_or_link_type_indicator(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);
        $uploaded = VaultDocument::create([
            'member_id' => $member->id, 'mentor_id' => $mentor->id,
            'title' => 'Documento com upload', 'file_path' => 'vault-documents/a.pdf',
        ]);
        $linked = VaultDocument::create([
            'member_id' => $member->id, 'mentor_id' => $mentor->id,
            'title' => 'Documento com link', 'file_url' => 'https://example.com/a.pdf',
        ]);

        $this->actingAs($this->admin());

        Livewire::test(ListVaultDocuments::class)
            ->assertCanSeeTableRecords([$uploaded, $linked])
            ->assertSee('Upload')
            ->assertSee('Link');
    }

    public function test_admin_can_create_a_document_with_an_external_link(): void
    {
        User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club', 'name' => 'Ricardo Mendes']);

        $this->actingAs($this->admin());

        Livewire::test(CreateVaultDocument::class)
            ->fillForm([
                'member_id' => $member->id,
                'title' => 'Insights da Sessão 4',
                'description' => 'Enviado pelo Douglas · 19 jun',
                'file_url' => 'https://example.com/insights.pdf',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('vault_documents', [
            'member_id' => $member->id,
            'title' => 'Insights da Sessão 4',
        ]);
    }

    public function test_creating_a_document_sets_mentor_id_to_the_single_mentor_automatically(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);

        $this->actingAs($this->admin());

        Livewire::test(CreateVaultDocument::class)
            ->fillForm([
                'member_id' => $member->id,
                'title' => 'Documento qualquer',
                'file_url' => 'https://example.com/a.pdf',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('vault_documents', [
            'title' => 'Documento qualquer',
            'mentor_id' => $mentor->id,
        ]);
    }

    public function test_admin_can_upload_a_file_and_it_resolves_to_a_storage_path(): void
    {
        Storage::fake('local');
        User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);

        $this->actingAs($this->admin());

        Livewire::test(CreateVaultDocument::class)
            ->fillForm([
                'member_id' => $member->id,
                'title' => 'Documento com upload',
                'file_path' => UploadedFile::fake()->create('insights.pdf', 10, 'application/pdf'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $document = VaultDocument::where('title', 'Documento com upload')->firstOrFail();

        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_admin_can_edit_a_document(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);
        $document = VaultDocument::create([
            'member_id' => $member->id, 'mentor_id' => $mentor->id,
            'title' => 'Título original', 'file_url' => 'https://example.com/a.pdf',
        ]);

        $this->actingAs($this->admin());

        Livewire::test(EditVaultDocument::class, ['record' => $document->getKey()])
            ->fillForm(['title' => 'Título atualizado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('vault_documents', [
            'id' => $document->id,
            'title' => 'Título atualizado',
        ]);
    }

    public function test_admin_can_delete_a_document(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);
        $document = VaultDocument::create([
            'member_id' => $member->id, 'mentor_id' => $mentor->id,
            'title' => 'A remover', 'file_url' => 'https://example.com/a.pdf',
        ]);

        $this->actingAs($this->admin());

        Livewire::test(ListVaultDocuments::class)
            ->callTableAction(DeleteAction::class, record: $document);

        $this->assertDatabaseMissing('vault_documents', ['id' => $document->id]);
    }
}
