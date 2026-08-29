# NPS pós-encontro (issue #19) — feedback via modal na página de Encontros

Fecha a issue #19, desbloqueada pela #18 (Encontros ao vivo, já entregue). Adiciona um botão
"Avaliar" nos encontros passados de `/membros/encontros`, que abre um modal de nota 0–10 — mesmo
padrão visual do protótipo (`.nps-ov`/`.nps-card`), diferente do banner inline já usado pro NPS
pós-aula.

## 1. Contexto

Confirmado com o usuário:

- **Modal, não banner inline.** O protótipo usa um modal compartilhado (bottom-sheet:
  `resources/views/layouts/prototype.blade.php:64-74` + `.nps-ov`/`.nps-card` em `_styles.blade.php`)
  pros dois contextos de NPS (aula e encontro). O NPS pós-aula já entregue usa um banner inline
  embaixo do player — decisão diferente, tomada antes. Esta spec não mexe no NPS de aula; cria o
  padrão de modal só pro encontro. Fica uma inconsistência visual entre os dois triggers de NPS no
  app — registrada aqui, não é escopo desta issue resolver.
- **Tabela própria `EncontroFeedback`, não polimórfico.** Espelha `LessonFeedback`/
  `SubmitLessonNpsScore` (mesmo padrão já usado pra `Framework` espelhar `Lesson`) em vez de
  generalizar a tabela existente — não mexe em código de aula já testado em produção.
- **Só nota, sem comentário.** O protótipo tem um campo de comentário opcional no modal
  (`#npsComment`), mas a decisão já tomada pro NPS de aula foi nota-only ("feedback curto"); mantém
  consistência aqui.
- **Sem lógica de alerta pro Douglas.** O protótipo mostra um toast diferente pra nota ≤6 ("Douglas é
  avisado na hora") — isso é Painel do mentor (issue #21), fora de escopo.
- **Gatilho: botão "Avaliar", só em encontros passados**, igual ao protótico
  (`resources/views/prototype/encontros.blade.php:16`, dentro do `@if ($e['status'] === 'past')`).
  Diferente do NPS de aula (que dispara sozinho ao bater ~90% assistido), não há sinal de "presença"
  num encontro ao vivo — RSVP/lista de presença já está fora de escopo desde a spec de Encontros
  (§8), então o gatilho tem que ser manual.
- **Some depois de respondido.** Uma vez que o usuário já avaliou aquele encontro, o botão "Avaliar"
  não aparece mais nesse card — mesmo padrão de supressão (`hasFeedback`) já usado no NPS de aula.

## 2. Modelo de dados

Migration `create_encontro_feedback_table`:

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

## 4. `App\Livewire\Membros\Encontros`

Ganha um segundo computed e um método de ação — direto na classe, não numa trait compartilhada
(diferente do `TracksLessonProgress`, porque `Encontros` é a única página que usa isso; não há
duplicação a evitar entre componentes ainda):

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

## 5. UI

### Botão "Avaliar" no `<x-encontro-card>`

No ramo `@if ($encontro->isPast())`, ao lado de "Ver na biblioteca"/"Gravação em breve" (os dois
podem aparecer juntos — o encontro já aconteceu, avaliar não depende da gravação estar linkada):

```blade
@if (! in_array($encontro->id, $ratedEncontroIds))
    <button type="button" @click="$dispatch('open-nps-modal', { encontroId: {{ $encontro->id }} })"
            class="inline-flex items-center px-[11px] py-[5px] rounded-full text-[11px] font-bold uppercase tracking-[.1em] bg-paper text-stone border border-sand hover:border-black hover:text-ink">
        Avaliar
    </button>
@endif
```

`<x-encontro-card>` ganha um novo prop `:rated-encontro-ids="$this->ratedEncontroIds"` (array),
passado pela view — mais simples que computar disponibilidade por card individualmente no
componente Livewire.

### Modal (`<x-nps-modal>`, novo componente Blade)

Um único modal renderizado uma vez na view de Encontros (não um por card), state em Alpine, ouvindo
um evento customizado disparado por qualquer botão "Avaliar" da página:

```blade
<div
    x-data="{ open: false, encontroId: null, score: null }"
    x-on:open-nps-modal.window="open = true; encontroId = $event.detail.encontroId; score = null"
    x-show="open" x-cloak
    class="fixed inset-0 z-[150] bg-black/55 flex items-end sm:items-center justify-center p-[18px]"
>
    <div @click.outside="open = false" class="bg-card rounded-t-[22px] sm:rounded-[22px] p-[26px] max-w-[470px] w-full shadow-[0_24px_60px_rgba(0,0,0,.35)]">
        <h3 class="font-display text-lg">Como foi para você?</h3>
        <p class="mt-1 mb-4 text-sm text-stone">De 0 a 10, o quanto esse encontro te ajudou a decidir melhor?</p>

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
                @click="if (score !== null) { $wire.submitEncontroNpsScore(encontroId, score); open = false }"
                :disabled="score === null"
                class="ms-auto px-4 py-2 rounded-full bg-black text-white text-sm font-semibold disabled:opacity-40 disabled:cursor-not-allowed"
            >Enviar</button>
        </div>
    </div>
</div>
```

Incluído uma vez em `resources/views/livewire/membros/encontros.blade.php`, depois da lista. Ao
enviar, `$wire.submitEncontroNpsScore(...)` dispara um round-trip Livewire normal — como
`ratedEncontroIds()` é recomputado a cada request, o botão "Avaliar" já some do card certo no
próprio re-render, sem lógica extra no cliente.

## 6. Testes

- `Tests\Unit\SubmitEncontroNpsScoreTest`: espelha `SubmitLessonNpsScoreTest` (cria; reenviar
  atualiza em vez de duplicar; nota acima de 10 vira 10; nota abaixo de 0 vira 0).
- `Tests\Feature\Livewire\Membros\EncontrosTest` (adições): "Avaliar" só aparece em encontros
  passados (nunca em futuros); "Avaliar" some depois que o usuário já avaliou aquele encontro
  específico (mas continua aparecendo em outro encontro passado ainda não avaliado); chamar
  `submitEncontroNpsScore` persiste a nota em `encontro_feedback`; chamar com um `encontroId`
  inexistente não gera erro nem cria linha nenhuma.

## 7. Fora de escopo

- Campo de comentário (o protótipo tem, o NPS de aula não tem — mantém paridade com a decisão já
  tomada).
- Qualquer alerta/notificação pro mentor baseado na nota — Painel do mentor (#21).
- Unificar o padrão visual dos dois NPS (banner de aula vs. modal de encontro) — inconsistência
  conhecida, não resolvida aqui.
- RSVP/lista de presença como pré-requisito pra avaliar — qualquer membro CLUB pode avaliar
  qualquer encontro passado, sem verificação de presença (já fora de escopo desde a spec de
  Encontros).
