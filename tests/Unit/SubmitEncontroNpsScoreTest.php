<?php

namespace Tests\Unit;

use App\Actions\SubmitEncontroNpsScore;
use App\Models\Encontro;
use App\Models\EncontroFeedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmitEncontroNpsScoreTest extends TestCase
{
    use RefreshDatabase;

    private function encontro(): Encontro
    {
        return Encontro::create([
            'tema' => 'O comercial é gente', 'quem' => 'Com Douglas',
            'scheduled_at' => now()->subDay(),
        ]);
    }

    public function test_creates_feedback_with_the_given_score(): void
    {
        $user = User::factory()->create();
        $encontro = $this->encontro();

        (new SubmitEncontroNpsScore)->handle($user->id, $encontro->id, 9);

        $this->assertDatabaseHas('encontro_feedback', [
            'user_id' => $user->id,
            'encontro_id' => $encontro->id,
            'score' => 9,
        ]);
    }

    public function test_resubmitting_updates_the_existing_score_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        $encontro = $this->encontro();

        (new SubmitEncontroNpsScore)->handle($user->id, $encontro->id, 5);
        (new SubmitEncontroNpsScore)->handle($user->id, $encontro->id, 8);

        $this->assertSame(1, EncontroFeedback::query()->where('user_id', $user->id)->where('encontro_id', $encontro->id)->count());
        $this->assertDatabaseHas('encontro_feedback', ['user_id' => $user->id, 'encontro_id' => $encontro->id, 'score' => 8]);
    }

    public function test_score_above_10_is_clamped_to_10(): void
    {
        $user = User::factory()->create();
        $encontro = $this->encontro();

        (new SubmitEncontroNpsScore)->handle($user->id, $encontro->id, 99);

        $this->assertDatabaseHas('encontro_feedback', ['user_id' => $user->id, 'encontro_id' => $encontro->id, 'score' => 10]);
    }

    public function test_score_below_0_is_clamped_to_0(): void
    {
        $user = User::factory()->create();
        $encontro = $this->encontro();

        (new SubmitEncontroNpsScore)->handle($user->id, $encontro->id, -3);

        $this->assertDatabaseHas('encontro_feedback', ['user_id' => $user->id, 'encontro_id' => $encontro->id, 'score' => 0]);
    }
}
