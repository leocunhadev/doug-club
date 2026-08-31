# Radar: Start engajado pronto pro CLUB — Design

## Objetivo

Fechar a issue #50 (segunda metade de "Pontes sugeridas", desmembrada da #41): mostrar no Radar um card agregado com os membros `tier=start` que já assistiram todas as aulas disponíveis pro tier deles e baixaram 2+ frameworks distintos — sinal de que estão prontos pra receber um convite pessoal pro CLUB.

## Estado atual (contexto)

- `App\Models\Lesson::isAvailableFor(User $user)` retorna `$this->tier === 'start' || $user->hasClubAccess()` — ou seja, o catálogo acessível de um usuário `tier=start` é exatamente as `Lesson` com `tier='start'`.
- `App\Models\LessonProgress` (`user_id`, `lesson_id`, `status`, `last_watched_at`) já registra progresso; `status = 'completed'` já é gravado por `App\Actions\MarkLessonAsCompleted`, disparado pelo trait `App\Livewire\Concerns\TracksLessonProgress::markCompleted()` — mecanismo já em uso, não precisa de nada novo aqui.
- **Não existe rastreamento de download de framework hoje.** `App\Http\Controllers\Membros\FrameworkPdfDownloadController` só faz `Storage::disk('public')->download(...)`, sem gravar nada no banco. A rota (`membros/frameworks/{framework}/download`, `routes/web.php:42-44`) é acessível a qualquer tier autenticado (`auth`, `verified`, `active` — sem `tier:...`).
- Precedente de padrão para "marcar interação ao servir um arquivo": `App\Http\Controllers\Membros\VaultDocumentOpenController::markOpened()` grava um timestamp no próprio registro antes do download/redirect. Não serve de molde direto aqui porque lá é 1 documento → 1 dono; aqui é N usuários × N frameworks, então precisa de uma tabela de log separada, não uma coluna no `Framework`.
- `App\Livewire\Membros\Radar` já tem a seção "Pontes sugeridas" (issue #41, computed `suggestedBridges()`) — este card entra na mesma seção, como um card adicional, agregado (não é uma lista de sugestões individuais como o match ensinar/aprender).

## Escopo

**Dentro do escopo:** rastreamento de download de framework (tabela nova + gravação no controller existente) e o card agregado de membros Start engajados no Radar.

**Fora do escopo:** qualquer fluxo de "convite mediado pelo mentor" (ex: botão que dispara e-mail de convite) — não existe hoje um caminho de upgrade iniciado pelo mentor; o caminho real é o próprio membro aplicar pela página de Upgrade (issue #24). O card é puramente informativo, pro mentor entrar em contato por fora do app.

## Arquitetura

### Rastreamento de download

Nova tabela `framework_downloads`:

```php
Schema::create('framework_downloads', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('framework_id')->constrained()->cascadeOnDelete();
    $table->timestamps();
});
```

Sem índice único — um mesmo usuário pode baixar o mesmo framework várias vezes (ex: perdeu o arquivo, baixou de novo); cada download vira uma linha, e a query de elegibilidade conta `framework_id` **distintos**, não linhas.

Model `App\Models\FrameworkDownload` (`$fillable = ['user_id', 'framework_id']`, relações `user(): BelongsTo` e `framework(): BelongsTo`) — mesmo padrão de todo model simples de log/pivot já existente no projeto (ex: `LessonProgress`, `BridgeRequest`).

`FrameworkPdfDownloadController` grava a linha logo antes do `Storage::disk('public')->download(...)`:

```php
FrameworkDownload::create([
    'user_id' => request()->user()->id,
    'framework_id' => $framework->id,
]);
```

### Critério de elegibilidade

`Radar::engagedStartMembers(): Collection` (novo `#[Computed]`):

1. Carrega todas as `Lesson` com `tier='start'` publicadas (mesmo filtro usado em `isAvailableFor()` — o catálogo completo de um membro Start).
2. Para cada `User` com `tier='start'`: verifica se existe `LessonProgress` com `status='completed'` para CADA uma dessas aulas (nenhuma faltando).
3. Conta `framework_id` distintos em `FrameworkDownload` para o mesmo usuário; exige 2 ou mais.
4. Retorna a coleção de `User` que passam nos dois critérios — sem limite artificial (diferente do card de match da #41, aqui não há motivo de densidade visual pra limitar, é uma lista simples de nomes).

```php
#[Computed]
public function engagedStartMembers(): Collection
{
    $startLessonIds = Lesson::query()
        ->where('tier', 'start')
        ->whereNotNull('published_at')
        ->pluck('id');

    if ($startLessonIds->isEmpty()) {
        return collect();
    }

    return User::query()
        ->where('tier', 'start')
        ->get()
        ->filter(function (User $member) use ($startLessonIds) {
            $completedCount = LessonProgress::query()
                ->where('user_id', $member->id)
                ->where('status', 'completed')
                ->whereIn('lesson_id', $startLessonIds)
                ->count();

            if ($completedCount < $startLessonIds->count()) {
                return false;
            }

            $distinctFrameworksDownloaded = FrameworkDownload::query()
                ->where('user_id', $member->id)
                ->distinct('framework_id')
                ->count('framework_id');

            return $distinctFrameworksDownloaded >= 2;
        })
        ->values();
}
```

### View

Na seção "Pontes sugeridas" do Radar (`resources/views/livewire/membros/radar.blade.php`), logo depois do `@forelse ($this->suggestedBridges as $match) ... @empty ... @endforelse` da issue #41, adiciona (só renderiza quando há membros elegíveis — sem estado vazio próprio, já que a ausência de card aqui não é uma informação relevante pro mentor, diferente da ausência de matches):

```blade
@if ($this->engagedStartMembers->isNotEmpty())
    <div class="match rounded-[18px] border border-sand bg-card shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)]">
        <div class="d">
            <b>{{ $this->engagedStartMembers->count() }} {{ Str::plural('membro', $this->engagedStartMembers->count()) }} Start</b>
            assistiram todas as aulas e baixaram 2+ frameworks: {{ $this->engagedStartMembers->pluck('name')->join(', ') }}.
            <em>Prontos para o convite ao CLUB.</em>
        </div>
    </div>
@endif
```

Sem avatares sobrepostos (`.duo`) aqui — esse elemento visual do protótipo representa um PAR de pessoas (aprendiz+professor), que não se aplica a uma lista de N membros; omitido em vez de forçado a caber.

## Casos de borda

- **Nenhuma `Lesson` com `tier='start'` publicada ainda**: `engagedStartMembers()` retorna coleção vazia sem rodar nenhuma query de progresso (guarda explícita) — evita o caso degenerado de "0 aulas completadas ≥ 0 aulas exigidas" contar todo mundo como elegível.
- **Membro baixou o mesmo framework 3 vezes**: conta como 1 framework distinto, não 3 — usa `distinct('framework_id')->count('framework_id')`.
- **Novo framework publicado depois que o membro já tinha completado tudo**: não afeta a contagem de aulas (frameworks não entram no critério de "assistiu todas as aulas"), só entra na contagem de downloads se o membro baixar o PDF dele.
- **Framework excluído depois de ter sido baixado**: `framework_id` tem `cascadeOnDelete()` — a linha de download some junto, o que é o comportamento correto (não faz sentido contar download de um framework que não existe mais).

## Testes

- `FrameworkPdfDownloadController`: novo teste garantindo que baixar um framework cria uma linha em `framework_downloads` com o `user_id`/`framework_id` certos; teste garantindo que baixar o mesmo framework duas vezes cria duas linhas (não é upsert).
- `Radar::engagedStartMembers()`: membro que completou todas as aulas Start e baixou 2 frameworks distintos aparece; membro que completou todas mas baixou só 1 framework não aparece; membro que baixou 2+ frameworks mas não completou todas as aulas não aparece; membro `tier=club`/`mentor` nunca aparece mesmo que hipoteticamente tivesse os dados (não deveria ter, mas a query já filtra por tier); catálogo sem nenhuma `Lesson` `tier=start` retorna vazio sem erro; re-download do mesmo framework não conta como 2 frameworks distintos.
- View: o card aparece com a contagem e os nomes certos quando há membros elegíveis; não aparece nada quando não há.
