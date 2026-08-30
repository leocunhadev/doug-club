<?php

namespace Tests\Unit;

use App\Models\MentorAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentorAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_mentor_relationship_resolves(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $block = MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '12:00',
        ]);

        $this->assertTrue($block->mentor->is($mentor));
    }

    public function test_start_and_end_time_are_cast_to_hi_format(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $block = MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '12:00',
        ]);

        $this->assertSame('09:00', $block->start_time->format('H:i'));
        $this->assertSame('12:00', $block->end_time->format('H:i'));
    }

    public function test_block_is_deleted_when_the_mentor_is_deleted(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $block = MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '12:00',
        ]);

        $mentor->delete();

        $this->assertDatabaseMissing('mentor_availabilities', ['id' => $block->id]);
    }
}
