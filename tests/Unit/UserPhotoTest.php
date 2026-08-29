<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_url_is_null_when_no_photo_is_set(): void
    {
        $user = User::factory()->create(['photo_path' => null]);

        $this->assertNull($user->photo_url);
    }

    public function test_photo_url_resolves_to_the_public_disk_url_when_a_photo_is_set(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['photo_path' => 'avatars/example.jpg']);

        $this->assertSame(
            Storage::disk('public')->url('avatars/example.jpg'),
            $user->photo_url,
        );
    }
}
