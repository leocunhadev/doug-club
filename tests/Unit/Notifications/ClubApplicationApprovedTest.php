<?php

namespace Tests\Unit\Notifications;

use App\Models\User;
use App\Notifications\ClubApplicationApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubApplicationApprovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_sent_only_via_mail(): void
    {
        $user = User::factory()->create();

        $this->assertSame(['mail'], (new ClubApplicationApproved)->via($user));
    }

    public function test_mail_message_has_the_expected_subject_and_action_url(): void
    {
        $user = User::factory()->create(['name' => 'Carla Nunes']);

        $mail = (new ClubApplicationApproved)->toMail($user);

        $this->assertSame('Você foi aprovado pro CLUB!', $mail->subject);
        $this->assertSame(route('dashboard'), $mail->actionUrl);
    }
}
