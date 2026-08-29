<?php

namespace Tests\Feature\Membros;

use App\Models\Framework;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FrameworkPdfDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function framework(array $overrides = []): Framework
    {
        return Framework::create(array_merge([
            'code' => '4S', 'title' => 'Consumidor 4S', 'description' => 'Teste', 'position' => 10,
        ], $overrides));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $framework = $this->framework(['pdf_path' => 'framework-pdfs/x.pdf']);

        $this->get(route('membros.frameworks.download', $framework))
            ->assertRedirect(route('login'));
    }

    public function test_start_tier_member_can_download(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->create('4s.pdf', 10, 'application/pdf')
            ->store('framework-pdfs', 'public');

        $framework = $this->framework(['pdf_path' => $path]);

        $this->actingAs(User::factory()->create(['tier' => 'start']));

        $this->get(route('membros.frameworks.download', $framework))
            ->assertOk()
            ->assertDownload('Consumidor 4S.pdf');
    }

    public function test_returns_404_without_an_uploaded_file(): void
    {
        $framework = $this->framework(['pdf_url' => 'https://example.com/4s.pdf']);

        $this->actingAs(User::factory()->create());

        $this->get(route('membros.frameworks.download', $framework))
            ->assertNotFound();
    }

    public function test_returns_404_when_the_file_is_missing_from_disk(): void
    {
        Storage::fake('public');

        $framework = $this->framework(['pdf_path' => 'framework-pdfs/does-not-exist.pdf']);

        $this->actingAs(User::factory()->create());

        $this->get(route('membros.frameworks.download', $framework))
            ->assertNotFound();
    }
}
