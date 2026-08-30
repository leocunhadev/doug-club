<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response
            ->assertOk()
            ->assertSeeVolt('profile.update-profile-information-form')
            ->assertSeeVolt('profile.update-password-form')
            ->assertSeeVolt('profile.delete-user-form');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.update-profile-information-form')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->call('updateProfileInformation');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.update-profile-information-form')
            ->set('name', 'Test User')
            ->set('email', $user->email)
            ->call('updateProfileInformation');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.delete-user-form')
            ->set('password', 'wrong-password')
            ->call('deleteUser');

        $component
            ->assertHasErrors('password')
            ->assertNoRedirect();

        $this->assertNotNull($user->fresh());
    }

    public function test_user_can_upload_a_profile_photo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['photo_path' => null]);

        $this->actingAs($user);

        $component = Volt::test('profile.update-profile-information-form')
            ->set('name', $user->name)
            ->set('email', $user->email)
            ->set('photo', UploadedFile::fake()->image('avatar.jpg'));

        $component->assertHasNoErrors();

        $user->refresh();

        $this->assertNotNull($user->photo_path);
        Storage::disk('public')->assertExists($user->photo_path);
    }

    public function test_uploading_a_new_photo_deletes_the_previous_one(): void
    {
        Storage::fake('public');

        $oldPath = UploadedFile::fake()->image('old.jpg')->store('avatars', 'public');
        $user = User::factory()->create(['photo_path' => $oldPath]);

        $this->actingAs($user);

        Volt::test('profile.update-profile-information-form')
            ->set('name', $user->name)
            ->set('email', $user->email)
            ->set('photo', UploadedFile::fake()->image('new.jpg'))
            ->assertHasNoErrors();

        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_photo_upload_rejects_non_image_files(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['photo_path' => null]);

        $this->actingAs($user);

        Volt::test('profile.update-profile-information-form')
            ->set('photo', UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'))
            ->assertHasErrors('photo');

        $this->assertNull($user->fresh()->photo_path);
    }

    public function test_photo_upload_rejects_files_over_2mb(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['photo_path' => null]);

        $this->actingAs($user);

        Volt::test('profile.update-profile-information-form')
            ->set('photo', UploadedFile::fake()->image('big.jpg')->size(3000))
            ->assertHasErrors('photo');

        $this->assertNull($user->fresh()->photo_path);
    }

    public function test_header_shows_the_photo_when_the_user_has_one(): void
    {
        Storage::fake('public');

        $path = UploadedFile::fake()->image('avatar.jpg')->store('avatars', 'public');
        $user = User::factory()->create(['photo_path' => $path]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertSee(Storage::disk('public')->url($path), false);
    }

    public function test_header_shows_initials_when_the_user_has_no_photo(): void
    {
        $user = User::factory()->create(['name' => 'Ana Beatriz', 'photo_path' => null]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertSee('AB');
    }

    public function test_user_can_remove_their_profile_photo(): void
    {
        Storage::fake('public');

        $path = UploadedFile::fake()->image('avatar.jpg')->store('avatars', 'public');
        $user = User::factory()->create(['photo_path' => $path]);

        $this->actingAs($user);

        Volt::test('profile.update-profile-information-form')
            ->call('removePhoto')
            ->assertHasNoErrors();

        $this->assertNull($user->fresh()->photo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_network_profile_section_is_visible_for_club_tier(): void
    {
        $user = User::factory()->create(['tier' => 'club']);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertSee('Perfil na rede CLUB');
    }

    public function test_network_profile_section_is_hidden_for_start_tier(): void
    {
        $user = User::factory()->create(['tier' => 'start']);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertDontSee('Perfil na rede CLUB');
    }

    public function test_network_profile_can_be_updated(): void
    {
        $user = User::factory()->create(['tier' => 'club']);

        $this->actingAs($user);

        Volt::test('profile.update-profile-information-form')
            ->set('company', 'Estúdio Beta')
            ->set('bio', 'Ajudo empresas a crescer com dados.')
            ->set('teachTagsInput', 'Vendas, Copywriting')
            ->set('learnTagsInput', 'Gestão de equipe')
            ->call('updateNetworkProfile')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame('Estúdio Beta', $user->company);
        $this->assertSame('Ajudo empresas a crescer com dados.', $user->bio);
        $this->assertSame(['Vendas', 'Copywriting'], $user->teach_tags);
        $this->assertSame(['Gestão de equipe'], $user->learn_tags);
    }

    public function test_network_profile_tags_are_trimmed_and_empty_entries_are_removed(): void
    {
        $user = User::factory()->create(['tier' => 'club']);

        $this->actingAs($user);

        Volt::test('profile.update-profile-information-form')
            ->set('teachTagsInput', 'Vendas,  Copywriting ,,Gestão')
            ->call('updateNetworkProfile')
            ->assertHasNoErrors();

        $this->assertSame(['Vendas', 'Copywriting', 'Gestão'], $user->fresh()->teach_tags);
    }

    public function test_network_profile_empty_tags_input_results_in_empty_array(): void
    {
        $user = User::factory()->create(['tier' => 'club', 'teach_tags' => ['Vendas']]);

        $this->actingAs($user);

        Volt::test('profile.update-profile-information-form')
            ->set('teachTagsInput', '')
            ->call('updateNetworkProfile')
            ->assertHasNoErrors();

        $this->assertSame([], $user->fresh()->teach_tags);
    }

    public function test_network_profile_fields_are_populated_on_mount(): void
    {
        $user = User::factory()->create([
            'tier' => 'club',
            'company' => 'Estúdio Beta',
            'bio' => 'Ajudo empresas a crescer com dados.',
            'teach_tags' => ['Vendas', 'Copywriting'],
            'learn_tags' => ['Gestão de equipe'],
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-profile-information-form')
            ->assertSet('company', 'Estúdio Beta')
            ->assertSet('bio', 'Ajudo empresas a crescer com dados.')
            ->assertSet('teachTagsInput', 'Vendas, Copywriting')
            ->assertSet('learnTagsInput', 'Gestão de equipe');
    }
}
