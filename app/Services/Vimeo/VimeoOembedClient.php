<?php

namespace App\Services\Vimeo;

use Illuminate\Support\Facades\Http;
use Throwable;

class VimeoOembedClient
{
    public function getDurationInSeconds(string $videoId): ?int
    {
        try {
            $response = Http::timeout(5)->get('https://vimeo.com/api/oembed.json', [
                'url' => "https://vimeo.com/{$videoId}",
            ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $duration = $response->json('duration');

        return is_int($duration) ? $duration : null;
    }
}
