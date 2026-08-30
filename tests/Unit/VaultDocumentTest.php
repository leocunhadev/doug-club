<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\VaultDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaultDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function document(array $overrides = []): VaultDocument
    {
        $member = User::factory()->create(['tier' => 'club']);
        $mentor = User::factory()->create(['tier' => 'mentor']);

        return VaultDocument::create(array_merge([
            'member_id' => $member->id, 'mentor_id' => $mentor->id,
            'title' => 'Insights da Sessão 4', 'description' => 'Enviado pelo Douglas · 19 jun',
        ], $overrides));
    }

    public function test_member_and_mentor_relationships_resolve(): void
    {
        $document = $this->document();

        $this->assertTrue($document->member->is(User::find($document->member_id)));
        $this->assertTrue($document->mentor->is(User::find($document->mentor_id)));
    }

    public function test_has_uploaded_file_is_true_when_file_path_is_set(): void
    {
        $document = $this->document(['file_path' => 'vault-documents/insights.pdf']);

        $this->assertTrue($document->hasUploadedFile());
    }

    public function test_has_uploaded_file_is_false_when_only_file_url_is_set(): void
    {
        $document = $this->document(['file_url' => 'https://example.com/insights.pdf']);

        $this->assertFalse($document->hasUploadedFile());
    }

    public function test_is_new_is_true_when_opened_at_is_null(): void
    {
        $document = $this->document(['opened_at' => null]);

        $this->assertTrue($document->isNew());
    }

    public function test_is_new_is_false_when_opened_at_is_set(): void
    {
        $document = $this->document(['opened_at' => now()]);

        $this->assertFalse($document->isNew());
    }

    public function test_icon_label_is_pdf_for_a_pdf_upload(): void
    {
        $document = $this->document(['file_path' => 'vault-documents/insights.pdf']);

        $this->assertSame('PDF', $document->icon_label);
    }

    public function test_icon_label_is_video_for_a_video_upload(): void
    {
        $document = $this->document(['file_path' => 'vault-documents/gravacao.mp4']);

        $this->assertSame('VÍDEO', $document->icon_label);
    }

    public function test_icon_label_is_xlsx_for_a_spreadsheet_upload(): void
    {
        $document = $this->document(['file_path' => 'vault-documents/tabela.xlsx']);

        $this->assertSame('XLSX', $document->icon_label);
    }

    public function test_icon_label_is_doc_for_a_word_upload(): void
    {
        $document = $this->document(['file_path' => 'vault-documents/plano.docx']);

        $this->assertSame('DOC', $document->icon_label);
    }

    public function test_icon_label_is_link_for_an_external_url_with_no_recognizable_extension(): void
    {
        $document = $this->document(['file_url' => 'https://vimeo.com/76979871']);

        $this->assertSame('LINK', $document->icon_label);
    }

    public function test_icon_label_is_arquivo_for_an_unrecognized_extension(): void
    {
        $document = $this->document(['file_path' => 'vault-documents/arquivo.zip']);

        $this->assertSame('ARQUIVO', $document->icon_label);
    }

    public function test_document_is_deleted_when_the_member_is_deleted(): void
    {
        $document = $this->document();
        $member = $document->member;

        $member->delete();

        $this->assertDatabaseMissing('vault_documents', ['id' => $document->id]);
    }
}
