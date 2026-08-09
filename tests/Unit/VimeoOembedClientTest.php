<?php

namespace Tests\Unit;

use App\Services\Vimeo\VimeoOembedClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VimeoOembedClientTest extends TestCase
{
    public function test_it_returns_the_duration_in_seconds_on_success(): void
    {
        Http::fake([
            'vimeo.com/api/oembed.json*' => Http::response(['duration' => 635], 200),
        ]);

        $client = new VimeoOembedClient();

        $this->assertSame(635, $client->getDurationInSeconds('123456789'));
    }

    public function test_it_returns_null_when_the_response_has_no_duration(): void
    {
        Http::fake([
            'vimeo.com/api/oembed.json*' => Http::response(['title' => 'Some video'], 200),
        ]);

        $client = new VimeoOembedClient();

        $this->assertNull($client->getDurationInSeconds('123456789'));
    }

    public function test_it_returns_null_on_a_non_successful_response(): void
    {
        Http::fake([
            'vimeo.com/api/oembed.json*' => Http::response(['error' => 'not found'], 404),
        ]);

        $client = new VimeoOembedClient();

        $this->assertNull($client->getDurationInSeconds('does-not-exist'));
    }

    public function test_it_returns_null_when_the_request_throws(): void
    {
        Http::fake([
            'vimeo.com/api/oembed.json*' => function (): never {
                throw new ConnectionException('Connection timed out');
            },
        ]);

        $client = new VimeoOembedClient();

        $this->assertNull($client->getDurationInSeconds('123456789'));
    }
}
