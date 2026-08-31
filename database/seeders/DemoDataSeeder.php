<?php

namespace Database\Seeders;

use App\Models\ClubApplication;
use App\Models\Course;
use App\Models\Encontro;
use App\Models\EncontroFeedback;
use App\Models\Framework;
use App\Models\FrameworkDownload;
use App\Models\Lesson;
use App\Models\LessonFeedback;
use App\Models\LessonMaterial;
use App\Models\LessonProgress;
use App\Models\MentorAvailability;
use App\Models\MentorCommitment;
use App\Models\MentorNote;
use App\Models\MentorSession;
use App\Models\User;
use App\Models\VaultDocument;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Fills in every model this project has, for manual/visual testing of every
 * feature built so far. Runs after LmsSeeder (needs its Courses/Lessons).
 *
 * Creates four club members (the prototype's "Gente do CLUB" cast, so the
 * Pessoas page and Radar's "Pontes sugeridas" match have real data to show)
 * and reuses the two users DatabaseSeeder already creates (test@example.com,
 * tier=start; admin@example.com, mentor+admin) for everything else.
 *
 * Only Ricardo Mendes gets sessions/dossiê/cofre/encontro data — he's the
 * one club member every other feature's demo data is built around. The
 * other three exist for Pessoas/Radar's people list and bridge matching.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $mentor = User::where('email', 'admin@example.com')->firstOrFail();
        $startMember = User::where('email', 'test@example.com')->firstOrFail();

        $clubMember = User::factory()->create([
            'name' => 'Ricardo Mendes',
            'email' => 'club@example.com',
            'password' => Hash::make('123456789'),
            'tier' => 'club',
            'company' => 'Mendes Log · Logística',
            'bio' => 'Assumiu o comercial da própria empresa. Faturamento de R$ 8M/ano.',
            'teach_tags' => ['funil de indicação'],
            'learn_tags' => ['precificação', 'discurso de venda'],
        ]);

        User::factory()->create([
            'name' => 'Marina Prado',
            'email' => 'marina@example.com',
            'password' => Hash::make('123456789'),
            'tier' => 'club',
            'company' => 'Clínicas Vitalle · Saúde',
            'bio' => 'Três unidades no Rio. Referência em precificação de serviços de saúde.',
            'teach_tags' => ['precificação', 'expansão'],
            'learn_tags' => ['marca pessoal'],
        ]);

        User::factory()->create([
            'name' => 'Caio Fonseca',
            'email' => 'caio@example.com',
            'password' => Hash::make('123456789'),
            'tier' => 'club',
            'company' => 'Grupo Andar · Imobiliário',
            'bio' => 'Segunda geração assumindo a empresa da família, em plena virada digital.',
            'teach_tags' => ['negociação de alto valor'],
            'learn_tags' => ['funil de indicação'],
        ]);

        User::factory()->create([
            'name' => 'Alessandra Ribeiro',
            'email' => 'alessandra@example.com',
            'password' => Hash::make('123456789'),
            'tier' => 'club',
            'company' => 'AR Odonto · Saúde',
            'bio' => 'Primeira mentorada do CLUB. Reposicionando a clínica para o premium.',
            'teach_tags' => ['experiência do paciente'],
            'learn_tags' => ['oferta premium'],
        ]);

        $frameworks = $this->seedFrameworks();
        $this->seedLessonMaterials();
        $this->completeAllStartLessonsFor($startMember);
        $this->seedLessonFeedback($clubMember);
        $this->seedFrameworkDownloads($startMember, $frameworks);
        $this->seedMentorAvailability($mentor);
        $this->seedMentorSessions($mentor, $clubMember);
        $this->seedDossies($mentor, $clubMember);
        $this->seedVaultDocuments($mentor, $clubMember);
        $this->seedClubApplication($startMember);
        $this->seedEncontros($clubMember);
    }

    /**
     * @return array<int, Framework>
     */
    private function seedFrameworks(): array
    {
        $lessonWithFramework = Lesson::where('tier', 'club')->first();

        $withRealFile = Framework::create([
            'code' => '4S',
            'title' => 'Consumidor 4S',
            'description' => 'O mapa pra entender o que o seu cliente realmente quer comprar.',
            'position' => 40,
            'lesson_id' => $lessonWithFramework?->id,
        ]);
        $pdfPath = 'framework-pdfs/consumidor-4s-demo.pdf';
        Storage::disk('public')->put($pdfPath, '%PDF-1.4 conteúdo de demonstração para teste visual.');
        $withRealFile->update(['pdf_path' => $pdfPath]);

        $withExternalLink = Framework::create([
            'code' => 'DOR',
            'title' => 'DOR: Direção, Orientação, Resultado',
            'description' => 'O roteiro de toda sessão 1:1 que vale a pena ter.',
            'pdf_url' => 'https://example.com/frameworks/dor.pdf',
            'position' => 30,
        ]);

        $withoutPdfYet = Framework::create([
            'code' => 'PADRAO',
            'title' => 'Dado · Padrão · Decisão',
            'description' => 'Como transformar dado solto em decisão de verdade.',
            'position' => 20,
        ]);

        return [$withRealFile, $withExternalLink, $withoutPdfYet];
    }

    private function seedLessonMaterials(): void
    {
        $startLesson = Lesson::where('tier', 'start')->first();
        $clubLesson = Lesson::where('tier', 'club')->first();

        if ($startLesson) {
            LessonMaterial::create([
                'lesson_id' => $startLesson->id,
                'title' => 'Slides da aula',
                'file_url' => 'https://example.com/materiais/slides.pdf',
            ]);
        }

        if ($clubLesson) {
            $materialPath = 'lesson-materials/apostila-demo.pdf';
            Storage::disk('public')->put($materialPath, '%PDF-1.4 apostila de demonstração para teste visual.');

            LessonMaterial::create([
                'lesson_id' => $clubLesson->id,
                'title' => 'Apostila completa',
                'file_path' => $materialPath,
            ]);
        }
    }

    private function completeAllStartLessonsFor(User $member): void
    {
        Lesson::where('tier', 'start')->get()->each(function (Lesson $lesson) use ($member) {
            LessonProgress::updateOrCreate(
                ['user_id' => $member->id, 'lesson_id' => $lesson->id],
                ['status' => 'completed', 'watched_seconds' => $lesson->duration_seconds ?? 0, 'last_watched_at' => now()->subDays(2)],
            );
        });
    }

    private function seedLessonFeedback(User $clubMember): void
    {
        Lesson::orderByDesc('published_at')->take(3)->get()->each(function (Lesson $lesson) use ($clubMember) {
            LessonFeedback::create(['user_id' => $clubMember->id, 'lesson_id' => $lesson->id, 'score' => 9]);
        });
    }

    /**
     * @param  array<int, Framework>  $frameworks
     */
    private function seedFrameworkDownloads(User $startMember, array $frameworks): void
    {
        FrameworkDownload::create(['user_id' => $startMember->id, 'framework_id' => $frameworks[0]->id]);
        FrameworkDownload::create(['user_id' => $startMember->id, 'framework_id' => $frameworks[1]->id]);
    }

    private function seedMentorAvailability(User $mentor): void
    {
        foreach ([1, 3, 5] as $dayOfWeek) {
            MentorAvailability::create([
                'mentor_id' => $mentor->id,
                'day_of_week' => $dayOfWeek,
                'start_time' => '09:00',
                'end_time' => '12:00',
            ]);
        }
    }

    private function seedMentorSessions(User $mentor, User $clubMember): void
    {
        // Today, for Radar's "sessões hoje" KPI and the Agenda/Dashboard
        // "sua próxima sessão" cards.
        MentorSession::create([
            'mentor_id' => $mentor->id,
            'member_id' => $clubMember->id,
            'scheduled_at' => now()->setTime(10, 0),
        ]);

        // A past, cancelled session — exercises the Filament sessions table
        // and confirms cancelled sessions never block a future rebooking.
        MentorSession::create([
            'mentor_id' => $mentor->id,
            'member_id' => $clubMember->id,
            'scheduled_at' => now()->subDays(10)->setTime(11, 0),
            'cancelled_at' => now()->subDays(11),
        ]);
    }

    private function seedDossies(User $mentor, User $clubMember): void
    {
        MentorNote::create([
            'member_id' => $clubMember->id, 'mentor_id' => $mentor->id,
            'title' => 'Nota antiga', 'body' => 'Primeira conversa: mapeamos os principais bloqueios.',
        ])->forceFill(['created_at' => now()->subDays(20)])->save();

        MentorNote::create([
            'member_id' => $clubMember->id, 'mentor_id' => $mentor->id,
            'title' => 'Decisão do comercial', 'body' => 'Decidiu assumir o discurso de venda. Combinamos: gravar 3 conversas até a próxima sessão.',
        ]);

        MentorCommitment::create(['member_id' => $clubMember->id, 'text' => 'Gravar 3 conversas de venda']);
    }

    private function seedVaultDocuments(User $mentor, User $clubMember): void
    {
        VaultDocument::create([
            'member_id' => $clubMember->id, 'mentor_id' => $mentor->id,
            'title' => 'Planilha de precificação', 'file_url' => 'https://example.com/cofre/precificacao.xlsx',
        ]);

        $vaultPath = 'vault-documents/contrato-modelo-demo.pdf';
        Storage::disk('local')->put($vaultPath, '%PDF-1.4 contrato modelo de demonstração para teste visual.');
        VaultDocument::create([
            'member_id' => $clubMember->id, 'mentor_id' => $mentor->id,
            'title' => 'Contrato modelo', 'file_path' => $vaultPath,
            'opened_at' => now()->subDays(5),
        ]);
    }

    private function seedClubApplication(User $startMember): void
    {
        ClubApplication::create(['user_id' => $startMember->id]);
    }

    private function seedEncontros(User $clubMember): void
    {
        $course = Course::first();
        $recordingLesson = $course?->lessons()->first();

        $past = Encontro::create([
            'tema' => 'O comercial é gente',
            'quem' => 'Com Douglas',
            'scheduled_at' => now()->subDays(7),
            'recording_lesson_id' => $recordingLesson?->id,
        ]);
        EncontroFeedback::create(['user_id' => $clubMember->id, 'encontro_id' => $past->id, 'score' => 9]);

        Encontro::create([
            'tema' => 'Precificação sem medo',
            'quem' => 'Com Douglas',
            'scheduled_at' => now()->addDays(4)->setTime(19, 0),
            'access_url' => 'https://example.com/encontros/precificacao-sem-medo',
        ]);
    }
}
