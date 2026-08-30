<?php

namespace App\Actions;

class ParseVideoEmbedUrl
{
    /**
     * @return array{provider: string, video_id: string}|null
     */
    public function handle(string $url): ?array
    {
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) {
            return ['provider' => 'youtube', 'video_id' => $matches[1]];
        }

        if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
            return ['provider' => 'vimeo', 'video_id' => $matches[1]];
        }

        return null;
    }
}
