<?php

namespace Tests\Unit;

use App\Actions\ParseVideoEmbedUrl;
use Tests\TestCase;

class ParseVideoEmbedUrlTest extends TestCase
{
    public function test_parses_a_standard_youtube_watch_url(): void
    {
        $result = (new ParseVideoEmbedUrl)->handle('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        $this->assertSame(['provider' => 'youtube', 'video_id' => 'dQw4w9WgXcQ'], $result);
    }

    public function test_parses_a_short_youtu_be_url(): void
    {
        $result = (new ParseVideoEmbedUrl)->handle('https://youtu.be/dQw4w9WgXcQ');

        $this->assertSame(['provider' => 'youtube', 'video_id' => 'dQw4w9WgXcQ'], $result);
    }

    public function test_parses_a_youtube_url_with_extra_query_params(): void
    {
        $result = (new ParseVideoEmbedUrl)->handle('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=30s');

        $this->assertSame(['provider' => 'youtube', 'video_id' => 'dQw4w9WgXcQ'], $result);
    }

    public function test_parses_a_standard_vimeo_url(): void
    {
        $result = (new ParseVideoEmbedUrl)->handle('https://vimeo.com/76979871');

        $this->assertSame(['provider' => 'vimeo', 'video_id' => '76979871'], $result);
    }

    public function test_parses_a_vimeo_url_with_a_trailing_slash(): void
    {
        $result = (new ParseVideoEmbedUrl)->handle('https://vimeo.com/76979871/');

        $this->assertSame(['provider' => 'vimeo', 'video_id' => '76979871'], $result);
    }

    public function test_returns_null_for_an_unrecognized_url(): void
    {
        $result = (new ParseVideoEmbedUrl)->handle('https://example.com/some-video');

        $this->assertNull($result);
    }

    public function test_returns_null_for_an_empty_string(): void
    {
        $result = (new ParseVideoEmbedUrl)->handle('');

        $this->assertNull($result);
    }
}
