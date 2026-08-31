<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.login');
    }

    public function test_login_screen_renders_in_portuguese(): void
    {
        $this->get('/login')
            ->assertSee('E-mail')
            ->assertSee('Senha')
            ->assertSee('Lembrar de mim')
            ->assertSee('Esqueceu sua senha?')
            ->assertSee('Entrar')
            ->assertDontSee('Remember me')
            ->assertDontSee('Log in');
    }

    public function test_login_screen_uses_the_wordmark_logo_instead_of_the_icon_logo(): void
    {
        $this->get('/login')
            ->assertSee('id="wmark"', false)
            ->assertSee('DO.ing <span>CLUB</span>', false)
            ->assertDontSee('aria-label="DO.ing Club"', false);
    }

    public function test_login_screen_shows_the_branded_dark_layout(): void
    {
        $this->get('/login')
            ->assertSee('Decisão Orientada. Tudo é gente.')
            ->assertSee('Acesso individual e intransferível');
    }

    public function test_invalid_credentials_show_a_portuguese_error_message(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'wrong-password');

        $component->call('login');

        $component->assertHasErrors('form.email');
        $this->assertSame('Essas credenciais não correspondem aos nossos registros.', $component->errors()->first('form.email'));
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password');

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'wrong-password');

        $component->call('login');

        $component
            ->assertHasErrors()
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_users_with_revoked_access_cannot_authenticate(): void
    {
        $user = User::factory()->create(['access_revoked_at' => now()]);

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password');

        $component->call('login');

        $component
            ->assertHasErrors('form.email')
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_signup_button_renders_when_payment_link_is_configured(): void
    {
        config(['services.abacatepay.payment_link_url' => 'https://app.abacatepay.com/pay/bill_test123']);

        $this->get('/login')
            ->assertSee('https://app.abacatepay.com/pay/bill_test123', false);
    }

    public function test_signup_button_is_hidden_when_payment_link_is_not_configured(): void
    {
        config(['services.abacatepay.payment_link_url' => null]);

        $this->get('/login')
            ->assertDontSee('Quero fazer parte');
    }

    public function test_mentor_tier_lands_on_the_mentor_radar_after_login(): void
    {
        $user = User::factory()->create(['tier' => 'mentor']);

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password');

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('mentor.radar', absolute: false));
    }

    public function test_root_redirects_mentor_tier_to_the_mentor_radar(): void
    {
        $user = User::factory()->create(['tier' => 'mentor']);
        $this->actingAs($user);

        $this->get('/')->assertRedirect(route('mentor.radar'));
    }

    public function test_root_shows_the_dashboard_for_club_tier(): void
    {
        $user = User::factory()->create(['tier' => 'club']);
        $this->actingAs($user);

        $this->get('/')->assertOk();
    }
}
