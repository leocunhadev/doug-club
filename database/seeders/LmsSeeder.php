<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Database\Seeder;

class LmsSeeder extends Seeder
{
    /**
     * Seed courses and lessons that mirror the reference LMS layout.
     */
    public function run(): void
    {
        $courses = [
            [
                'label' => 'Boas Vindas',
                'title' => '',
                'description' => null,
                'position' => 50,
                'lessons' => [
                    ['number' => 1, 'title' => 'Boas Vindas ao Estabilidade não Existe', 'duration_seconds' => 127, 'published_at' => '2026-04-01'],
                ],
            ],
            [
                'label' => 'Módulo 4',
                'title' => 'Modelos de Negócio',
                'description' => 'A estrutura para sua empresa crescer e não entrar na estatística dos 80% que quebram.',
                'position' => 40,
                'lessons' => [
                    ['number' => 12, 'title' => 'Modelos de Negócio - Aula 12', 'duration_seconds' => 3120, 'published_at' => '2026-08-05'],
                    ['number' => 11, 'title' => 'Modelos de Negócio - Aula 11', 'duration_seconds' => 2870, 'published_at' => '2026-08-04'],
                    ['number' => 10, 'title' => 'Modelos de Negócio - Aula 10', 'duration_seconds' => 3510, 'published_at' => '2026-08-03'],
                    ['number' => 9, 'title' => 'Modelos de Negócio - Aula 09', 'duration_seconds' => 2990, 'published_at' => '2026-07-22'],
                    ['number' => 8, 'title' => 'Modelos de Negócio - Aula 08', 'duration_seconds' => 3340, 'published_at' => '2026-07-21'],
                    ['number' => 7, 'title' => 'Modelos de Negócio - Aula 07', 'duration_seconds' => 3080, 'published_at' => '2026-07-20'],
                    ['number' => 6, 'title' => 'Modelos de Negócio - Aula 06', 'duration_seconds' => 2755, 'published_at' => '2026-07-19'],
                    ['number' => 5, 'title' => 'Estabilidade Não Existe - Modelo de Negócios - Aula 05', 'duration_seconds' => 2923, 'published_at' => '2026-07-17'],
                    ['number' => 4, 'title' => 'Estabilidade Não Existe - Modelo de Negócios - Aula 04', 'duration_seconds' => 4020, 'published_at' => '2026-07-16'],
                    ['number' => 3, 'title' => 'Estabilidade Não Existe - Modelo de Negócios - Aula 03', 'duration_seconds' => 3300, 'published_at' => '2026-07-15'],
                ],
            ],
            [
                'label' => 'Curso 3',
                'title' => 'Liderança e Recrutamento',
                'description' => null,
                'position' => 30,
                'lessons' => [
                    ['number' => 4, 'title' => 'Estabilidade Não Existe - Formação de Times de Vendas - Aula 04', 'duration_seconds' => 3660, 'published_at' => '2026-06-11'],
                    ['number' => 3, 'title' => 'Estabilidade Não Existe - Formação de Times de Vendas - Aula 03', 'duration_seconds' => 3400, 'published_at' => '2026-06-10'],
                    ['number' => 2, 'title' => 'Estabilidade Não Existe - Formação de Times de Vendas - Aula 02', 'duration_seconds' => 3300, 'published_at' => '2026-06-09'],
                ],
            ],
            [
                'label' => 'Curso 2',
                'title' => 'Influência',
                'description' => null,
                'position' => 20,
                'lessons' => [
                    ['number' => 5, 'title' => 'Estabilidade Não Existe - Influência - Aula 05', 'duration_seconds' => 3960, 'published_at' => '2026-05-15'],
                    ['number' => 4, 'title' => 'Estabilidade Não Existe - Influência - Aula 04', 'duration_seconds' => 3455, 'published_at' => '2026-05-14'],
                    ['number' => 3, 'title' => 'Estabilidade Não Existe - Influência - Aula 03', 'duration_seconds' => 3300, 'published_at' => '2026-05-14'],
                ],
            ],
            [
                'label' => 'Curso 1',
                'title' => 'Vendas',
                'description' => null,
                'position' => 10,
                'lessons' => [
                    ['number' => 3, 'title' => 'Estabilidade Não Existe - Vendas - Aula 03', 'duration_seconds' => 3900, 'published_at' => '2026-04-15'],
                    ['number' => 2, 'title' => 'Estabilidade Não Existe - Vendas - Aula 02', 'duration_seconds' => 4200, 'published_at' => '2026-04-14'],
                    ['number' => 1, 'title' => 'Estabilidade Não Existe - Vendas - Aula 01', 'duration_seconds' => 3300, 'published_at' => '2026-04-13'],
                ],
            ],
        ];

        $demoVideos = [
            ['provider' => 'youtube', 'id' => 'aqz-KE-bpKQ'],
            ['provider' => 'vimeo', 'id' => '76979871'],
            ['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ'],
        ];
        $videoIndex = 0;

        foreach ($courses as $courseData) {
            $lessons = $courseData['lessons'];
            unset($courseData['lessons']);

            $course = Course::create($courseData);

            foreach ($lessons as $index => $lessonData) {
                $video = $demoVideos[$videoIndex % count($demoVideos)];
                $videoIndex++;

                $course->lessons()->create($lessonData + [
                    'video_provider' => $video['provider'],
                    'video_id' => $video['id'],
                    'position' => count($lessons) - $index,
                ]);
            }
        }

        $this->seedSampleProgress();
    }

    /**
     * A little watch history so the hero player and the "ASSISTINDO" badge
     * have something to show right after a fresh seed, without needing to
     * click around first.
     */
    private function seedSampleProgress(): void
    {
        $user = User::first();

        if (! $user) {
            return;
        }

        $completedLesson = Lesson::whereHas('course', fn ($q) => $q->where('label', 'Boas Vindas'))->first();
        $watchingLesson = Lesson::where('title', 'Estabilidade Não Existe - Modelo de Negócios - Aula 05')->first();

        if ($completedLesson) {
            LessonProgress::create([
                'user_id' => $user->id,
                'lesson_id' => $completedLesson->id,
                'status' => 'completed',
                'watched_seconds' => $completedLesson->duration_seconds,
                'last_watched_at' => now()->subDays(3),
            ]);
        }

        if ($watchingLesson) {
            LessonProgress::create([
                'user_id' => $user->id,
                'lesson_id' => $watchingLesson->id,
                'status' => 'watching',
                'watched_seconds' => 900,
                'last_watched_at' => now()->subHour(),
            ]);
        }
    }
}
