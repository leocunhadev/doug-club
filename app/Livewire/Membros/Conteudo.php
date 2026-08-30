<?php

namespace App\Livewire\Membros;

use App\Actions\ParseVideoEmbedUrl;
use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\Course;
use App\Models\Encontro;
use App\Models\Lesson;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Conteudo extends Component
{
    use ComputesUserInitials;

    public string $lessonTitle = '';

    public string $lessonVideoUrl = '';

    public string $lessonTier = 'start';

    public string $encontroTema = '';

    public string $encontroQuem = '';

    public string $encontroScheduledAt = '';

    public function publishLesson(ParseVideoEmbedUrl $parseVideoEmbedUrl): void
    {
        $this->validate([
            'lessonTitle' => ['required', 'string', 'max:255'],
            'lessonVideoUrl' => ['required', 'string'],
            'lessonTier' => ['required', 'in:start,club'],
        ]);

        $parsed = $parseVideoEmbedUrl->handle($this->lessonVideoUrl);

        if (! $parsed) {
            $this->addError('lessonVideoUrl', 'Não reconhecemos esse link. Cole um link do YouTube ou do Vimeo.');

            return;
        }

        $course = Course::query()->firstOrCreate(
            ['label' => 'Publicações rápidas'],
            ['title' => '', 'position' => 0],
        );

        $nextNumber = (int) Lesson::query()->where('course_id', $course->id)->max('number') + 1;

        Lesson::create([
            'course_id' => $course->id,
            'number' => $nextNumber,
            'title' => $this->lessonTitle,
            'video_provider' => $parsed['provider'],
            'video_id' => $parsed['video_id'],
            'published_at' => today(),
            'position' => 0,
            'category' => 'Encontros',
            'tier' => $this->lessonTier,
        ]);

        $this->reset('lessonTitle', 'lessonVideoUrl', 'lessonTier');

        session()->flash('conteudo-status', 'Aula publicada na biblioteca.');
    }

    public function publishEncontro(): void
    {
        $this->validate([
            'encontroTema' => ['required', 'string', 'max:255'],
            'encontroQuem' => ['required', 'string', 'max:255'],
            'encontroScheduledAt' => ['required', 'date'],
        ]);

        $scheduledAt = Carbon::parse($this->encontroScheduledAt);

        if ($scheduledAt->isPast()) {
            $this->addError('encontroScheduledAt', 'A data do encontro precisa ser no futuro.');

            return;
        }

        Encontro::create([
            'tema' => $this->encontroTema,
            'quem' => $this->encontroQuem,
            'scheduled_at' => $scheduledAt,
        ]);

        $this->reset('encontroTema', 'encontroQuem', 'encontroScheduledAt');

        session()->flash('conteudo-status', 'Encontro publicado na agenda.');
    }

    public function render()
    {
        return view('livewire.membros.conteudo');
    }
}
