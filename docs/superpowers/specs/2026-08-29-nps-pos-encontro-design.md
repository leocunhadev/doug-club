# NPS unificado em modal compartilhado (issues #19 + #25)

Fecha as issues #19 (NPS pós-encontro, desbloqueada pela #18) e #25 (unificar NPS pós-aula no modal
compartilhado). As duas nascem juntas porque compartilham o mesmo componente: um único
`<x-nps-modal>`, incluído em cada página que precisa dele, que serve tanto o gatilho automático de
aula (ao bater ~90% assistido) quanto o gatilho manual de encontro (botão "Avaliar" num encontro
passado) — igual ao padrão do protótipo (`.nps-ov`/`.nps-card` em
`resources/views/layouts/prototype.blade.php:64-74`), que já era compartilhado entre os dois
contextos lá.

## 1. Contexto

Confirmado com o usuário:

- **Um modal só, pros dois contextos.** Substitui o banner inline que o NPS pós-aula usa hoje
  (`resources/views/components/lesson-player.blade.php`, `x-show="showNps"`) — não fica mais
  nenhum trigger de NPS em formato de banner no app.
- **Tabela própria `EncontroFeedback`, não polimórfico** — espelha `LessonFeedback`/
  `SubmitLessonNpsScore` (mesmo padrão já usado pra `Framework` espelhar `Lesson`). O NPS de aula
  continua com sua própria tabela; só a UI é compartilhada, não o modelo de dados.
- **Só nota, sem comentário** — o protótipo tem um campo de comentário opcional no modal, mas a
  decisão já tomada pro NPS de aula foi nota-only ("feedback curto"); mantém consistência nos dois
  contextos.
- **Sem lógica de alerta pro Douglas** — o protótipo mostra um toast diferente pra nota ≤6 ("Douglas
  é avisado na hora"); isso é Painel do mentor (issue #21), fora de escopo.
- **Gatilhos continuam diferentes, só a UI que abre é igual:**
  - Aula: automático, ao bater ~90% assistido (mesmo `checkCompleted()` que já marca como
    concluída) — `vimeo-progress.js` passa a disparar o modal em vez de setar um `showNps` que
    renderiza um banner sempre presente.
  - Encontro: manual, botão "Avaliar" só em encontros passados (não existe sinal de "presença" num
    encontro ao vivo — RSVP já está fora de escopo desde a spec de Encontros).
  - Nos dois casos: uma vez respondido, o gatilho correspondente (banner nunca mais aparece / botão
    "Avaliar" some) não aparece de novo pro mesmo item.

## 2. Modelo de dados

Migration `create_encontro_feedback_table` (nova tabela; `lesson_feedback` não muda):

```
encontro_feedback
  id
  user_id      FK -> users, cascadeOnDelete
  encontro_id  FK -> encontros, cascadeOnDelete
  score        unsignedTinyInteger  -- 0-10
  timestamps

  unique(user_id, encontro_id)
```

`EncontroFeedback` (model, mesma estrutura de `LessonFeedback`): `use HasFactory`, `$fillable` =
`user_id, encontro_id, score`. `user(): BelongsTo`, `encontro(): BelongsTo`.

## 3. Action

`App\Actions\SubmitEncontroNpsScore` — espelha `SubmitLessonNpsScore` linha por linha:

```php
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
```

`App\Actions\SubmitLessonNpsScore` já existe e não muda.

## 4. `<x-nps-modal>` — componente compartilhado

Um único componente Blade, sem props (todo o state vem do evento que o abre).

**Não pode entrar em `layouts/membros.blade.php`, fora do `{{ $slot }}`** — `$wire` no Alpine só
resolve dentro da árvore DOM que o Livewire realmente gerencia (o elemento raiz de cada página); um
elemento colocado como irmão de `{{ $slot }}`, fora do componente, faria `$wire[action](...)` falhar
silenciosamente. Por isso `<x-nps-modal>` é incluído dentro do template de cada página que precisa
dele — três lugares: `resources/views/livewire/membros/dashboard.blade.php`,
`resources/views/livewire/membros/aulas.blade.php` (as duas já renderizam `<x-lesson-player>`, que
é quem dispara o gatilho de aula) e `resources/views/livewire/membros/encontros.blade.php` (dispara
o gatilho de encontro). Continua sendo um componente único e reutilizável — só a inclusão que é por
página, não o código.

```blade
<div
    x-data="{ open: false, action: null, subjectId: null, subtitle: '', score: null }"
    x-on:open-nps-modal.window="
        open = true;
        action = $event.detail.action;
        subjectId = $event.detail.subjectId;
        subtitle = $event.detail.subtitle;
        score = null;
    "
    x-show="open" x-cloak
    class="fixed inset-0 z-[150] bg-black/55 flex items-end sm:items-center justify-center p-[18px]"
>
    <div @click.outside="open = false" class="bg-card rounded-t-[22px] sm:rounded-[22px] p-[26px] max-w-[470px] w-full shadow-[0_24px_60px_rgba(0,0,0,.35)]">
        <h3 class="font-display text-lg">Como foi para você?</h3>
        <p class="mt-1 mb-4 text-sm text-stone" x-text="subtitle"></p>

        <div class="flex flex-wrap gap-1.5 mb-4">
            <template x-for="i in 11" :key="i">
                <button
                    type="button"
                    @click="score = i - 1"
                    :class="score === i - 1 ? 'bg-brand border-brand text-white' : 'bg-card border-sand text-ink'"
                    class="w-9 h-[38px] rounded-[10px] border font-bold text-sm"
                ><span x-text="i - 1"></span></button>
            </template>
        </div>

        <div class="flex items-center gap-2.5">
            <button type="button" @click="open = false" class="text-sm text-stone hover:text-ink">Agora não</button>
            <button
                type="button"
                @click="if (score !== null) { $wire[action](subjectId, score); open = false }"
                :disabled="score === null"
                class="ms-auto px-4 py-2 rounded-full bg-black text-white text-sm font-semibold disabled:opacity-40 disabled:cursor-not-allowed"
            >Enviar</button>
        </div>
    </div>
</div>
```

`$wire[action](subjectId, score)` — chamada dinâmica de método no proxy `$wire` do Livewire/Alpine;
`action` é o nome do método (`'submitNpsScore'` ou `'submitEncontroNpsScore'`), sempre um método que
já existe no componente Livewire da página atual — o modal não precisa saber qual página o abriu.

### Gatilho de aula (retrofit)

Em `resources/views/components/lesson-player.blade.php`, remove o `<div x-show="showNps" ...>` do
banner inteiro (título, botões numerados, "Agora não" — tudo isso já existe no `<x-nps-modal>`
agora). Em `resources/js/vimeo-progress.js`, `checkCompleted()` troca:

```js
if (!hasFeedback) {
    this.showNps = true;
}
```

por:

```js
if (!hasFeedback) {
    window.dispatchEvent(new CustomEvent('open-nps-modal', {
        detail: {
            action: 'submitNpsScore',
            subjectId: lessonId,
            subtitle: 'De 0 a 10, o quanto essa aula te ajudou a decidir melhor?',
        },
    }));
}
```

`submitNps(score)` (o método que existia só pra fechar o banner e chamar `$wire.submitNpsScore`) sai
do `vimeo-progress.js` — o modal cuida disso sozinho agora. `showNps` também sai do state do
componente.

### Gatilho de encontro (`<x-encontro-card>`)

No ramo `@if ($encontro->isPast())`, ao lado de "Ver na biblioteca"/"Gravação em breve" (os dois
podem aparecer juntos — avaliar não depende da gravação estar linkada):

```blade
@if (! in_array($encontro->id, $ratedEncontroIds))
    <button
        type="button"
        @click="window.dispatchEvent(new CustomEvent('open-nps-modal', { detail: {
            action: 'submitEncontroNpsScore',
            subjectId: {{ $encontro->id }},
            subtitle: 'De 0 a 10, o quanto esse encontro te ajudou a decidir melhor?',
        } }))"
        class="inline-flex items-center px-[11px] py-[5px] rounded-full text-[11px] font-bold uppercase tracking-[.1em] bg-paper text-stone border border-sand hover:border-black hover:text-ink"
    >Avaliar</button>
@endif
```

`<x-encontro-card>` ganha um novo prop `ratedEncontroIds` (array de ints), passado de
`resources/views/livewire/membros/encontros.blade.php` como `:rated-encontro-ids="$this->ratedEncontroIds"`.

## 5. `App\Livewire\Membros\Encontros`

Ganha um segundo computed e um método de ação — direto na classe, não numa trait compartilhada
(diferente do `TracksLessonProgress`, porque `Encontros` é a única página que precisa disso hoje):

```php
#[Computed]
public function ratedEncontroIds(): array
{
    return EncontroFeedback::query()
        ->where('user_id', Auth::id())
        ->pluck('encontro_id')
        ->all();
}

public function submitEncontroNpsScore(int $encontroId, int $score, SubmitEncontroNpsScore $action): void
{
    if (! Encontro::query()->whereKey($encontroId)->exists()) {
        return;
    }

    $action->handle(Auth::id(), $encontroId, $score);
}
```

Sem checagem de tier no `submitEncontroNpsScore` — a página inteira já está atrás de `tier:club`
(mesmo raciocínio já documentado no card: qualquer um que chegue até aqui tem `hasClubAccess()`,
então checar de novo seria código morto).

`App\Livewire\Concerns\TracksLessonProgress::submitNpsScore()` já existe e não muda — o modal chama
ele do mesmo jeito que chamava antes, só que agora vindo de um clique num botão dentro do modal em
vez de um clique num botão dentro do banner.

## 6. Testes

- `Tests\Unit\SubmitEncontroNpsScoreTest`: espelha `SubmitLessonNpsScoreTest` (cria; reenviar
  atualiza em vez de duplicar; nota acima de 10 vira 10; nota abaixo de 0 vira 0).
- `Tests\Feature\Livewire\Membros\EncontrosTest` (adições): "Avaliar" só aparece em encontros
  passados (nunca em futuros); "Avaliar" some depois que o usuário já avaliou aquele encontro
  específico (mas continua aparecendo em outro encontro passado ainda não avaliado); chamar
  `submitEncontroNpsScore` persiste a nota em `encontro_feedback`; chamar com um `encontroId`
  inexistente não gera erro nem cria linha nenhuma.
- `Tests\Feature\Livewire\Membros\DashboardTest` (ajuste, não adição): os dois testes existentes
  `test_hero_player_passes_has_feedback_false_when_the_user_has_not_rated_the_lesson` /
  `..._true_when_the_user_already_rated_the_lesson` continuam exatamente iguais — ainda afirmam
  `hasFeedback: false`/`hasFeedback: true` no `x-data="vimeoProgress(...)"`, porque essa prop não
  muda de nome nem de cálculo, só o que o JS faz com ela quando é `false` (agora dispara o modal em
  vez de setar `showNps`). Nenhum teste PHP verifica o modal abrindo de fato — isso é 100%
  client-side (`vimeo-progress.js`), fora do alcance do PHPUnit; a garantia do lado servidor é só
  que `hasFeedback` continua calculado certo.

## 7. Fora de escopo

- Campo de comentário (o protótipo tem, decisão foi não ter nos dois contextos).
- Qualquer alerta/notificação pro mentor baseado na nota — Painel do mentor (#21).
- RSVP/lista de presença como pré-requisito pra avaliar um encontro — qualquer membro CLUB pode
  avaliar qualquer encontro passado, sem verificação de presença (já fora de escopo desde a spec de
  Encontros).
