<?php

namespace Tests\Feature\Membros;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonaPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_switcher_does_not_render_for_non_admin_users(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get('/')
            ->assertOk()
            ->assertDontSee('class="planswitch"', false);
    }

    public function test_plan_switcher_renders_for_admin_users(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true, 'tier' => 'club']));

        $this->get('/')
            ->assertOk()
            ->assertSee('class="planswitch"', false)
            ->assertSee('Start')
            ->assertSee('CLUB')
            ->assertSee('Mentor');
    }

    public function test_non_admin_cannot_hit_the_preview_persona_route(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false, 'tier' => 'club']));

        $this->get(route('membros.preview-persona', ['tier' => 'start']))
            ->assertForbidden();
    }

    public function test_invalid_tier_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true, 'tier' => 'club']));

        $this->get(route('membros.preview-persona', ['tier' => 'gold']))
            ->assertNotFound();
    }

    public function test_admin_can_preview_a_different_persona_and_the_header_nav_reflects_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'tier' => 'club']);
        $this->actingAs($admin);

        $this->get(route('membros.preview-persona', ['tier' => 'start']))->assertRedirect(route('dashboard'));

        $html = $this->get('/')->assertOk()->getContent();

        // The full club-only nav is shown even when previewing as start,
        // but locked (with a padlock) — unlike the real club-tier nav.
        $this->assertStringContainsString('Meu cofre', $html);
        $this->assertStringContainsString('🔒', $html);
    }

    public function test_admin_previewing_start_sees_the_start_badge_on_the_wordmark(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'tier' => 'club']);
        $this->actingAs($admin);

        $this->get(route('membros.preview-persona', ['tier' => 'start']));

        $this->get('/')
            ->assertOk()
            ->assertSee('class="start-tag"', false);
    }

    public function test_previewing_the_admins_own_real_tier_clears_the_preview(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'tier' => 'club']);
        $this->actingAs($admin);

        $this->get(route('membros.preview-persona', ['tier' => 'start']));
        $this->get(route('membros.preview-persona', ['tier' => 'club']));

        $this->assertFalse(session()->has('admin_persona_preview'));

        $this->get('/')
            ->assertOk()
            ->assertDontSee('class="start-tag"', false);
    }

    public function test_switching_persona_lands_on_each_tiers_default_view(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'tier' => 'club']);
        $this->actingAs($admin);

        $this->get(route('membros.preview-persona', ['tier' => 'start']))
            ->assertRedirect(route('dashboard'));

        $this->get(route('membros.preview-persona', ['tier' => 'mentor']))
            ->assertRedirect(route('mentor.radar'));

        $this->get(route('membros.preview-persona', ['tier' => 'club']))
            ->assertRedirect(route('dashboard'));
    }

    public function test_preview_never_changes_the_real_tier_gated_route_access(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'tier' => 'club']);
        $this->actingAs($admin);

        $this->get(route('membros.preview-persona', ['tier' => 'mentor']));

        // Previewing "mentor" must not grant real access to the mentor-only route.
        $this->get('/mentor/radar')->assertRedirect(route('dashboard'));
    }
}
