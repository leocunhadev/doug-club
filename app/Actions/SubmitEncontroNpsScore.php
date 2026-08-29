<?php

namespace App\Actions;

use App\Models\EncontroFeedback;

class SubmitEncontroNpsScore
{
    public function handle(int $userId, int $encontroId, int $score): void
    {
        EncontroFeedback::query()->updateOrCreate(
            ['user_id' => $userId, 'encontro_id' => $encontroId],
            ['score' => max(0, min(10, $score))],
        );
    }
}
