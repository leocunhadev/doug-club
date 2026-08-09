# Spec — Retomar aula e marcar como concluída (Vimeo)

## 1. Contexto

O player em destaque do dashboard de membros ([dashboard.blade.php](../../../resources/views/livewire/membros/dashboard.blade.php)) hoje é só um `<iframe>` estático apontando pra `$lesson->embed_url`. O schema de `lesson_progress` já tem os campos `watched_seconds` e `last_watched_at` (ver [lms-spec.md](../../lms-spec.md), seção 3) com a intenção original de "posição para retomar o player", mas nada os preenche.

Esta spec cobre: usar o Vimeo Player SDK pra retomar a aula de onde a pessoa parou e marcar a aula como `completed` automaticamente ao assistir a maior parte dela. **YouTube fica de fora por decisão explícita** — as aulas em YouTube continuam sem tracking/retomada por enquanto.

O item "marcar aula como completed automaticamente (% assistido)" estava listado como fora de escopo em [lms-spec.md](../../lms-spec.md) (seção 9); esta spec o traz para dentro.

## 2. Decisão de arquitetura: eventos, não polling

Uma primeira versão deste desenho salvava `watched_seconds` a cada ~10s via `timeupdate` enquanto o vídeo tocava. Foi descartada: gera uma chamada ao servidor a cada 10s por pessoa assistindo, carga desnecessária. A versão final só chama o servidor em eventos discretos (pause, fim do vídeo, cruzar o limiar de conclusão) — nunca em polling contínuo.

**Trade-off aceito:** se a pessoa fechar a aba ou trocar de aula no meio de uma sessão de reprodução sem nunca pausar, a posição não é salva daquele trecho — ela retomaria do último `pause`/`ended` registrado. Julgado aceitável em troca de não sobrecarregar o servidor.

## 3. Componentes

- **Dependência nova:** `@vimeo/player` (pacote npm oficial), importado em [app.js](../../../resources/js/app.js) — hoje o arquivo está vazio.
- **Alpine:** `app.js` registra `Alpine.data('vimeoProgress', ...)` via `document.addEventListener('alpine:init', ...)` (padrão compatível com o Alpine que o próprio Livewire inicializa, sem precisar de setup adicional).
- **Blade:** o container do iframe no hero de `dashboard.blade.php` ganha `x-data="vimeoProgress({ lessonId, initialSeconds })"` e `wire:key="hero-player-{{ $lesson->id }}"`. A `wire:key` é o que permite trocar de aula no carrossel sem lógica manual de destroy/rebuild: ao mudar, o Livewire descarta o elemento antigo e cria um novo, e o `x-init` do Alpine reinicializa o player do zero para a nova aula.
- **Actions novas** (seguindo o padrão de [MarkLessonAsWatching.php](../../../app/Actions/MarkLessonAsWatching.php)):
  - `App\Actions\UpdateLessonWatchedSeconds` — upserta `watched_seconds` + `last_watched_at` em `lesson_progress` para `(userId, lessonId)`. Nunca rebaixa `status` de `completed` para `watching`.
  - `App\Actions\MarkLessonAsCompleted` — seta `status = 'completed'` em `lesson_progress` para `(userId, lessonId)`.
- **Livewire (`App\Livewire\Membros\Dashboard`):** dois métodos novos, `updateProgress(int $lessonId, int $seconds)` e `markCompleted(int $lessonId)`, que apenas injetam as actions acima e chamam `.handle(Auth::id(), $lessonId, ...)` — mesmo estilo de `watchLesson()` já existente.

## 4. Fluxo de dados

**Ao carregar o player:**
1. A Blade injeta o `watched_seconds` salvo do usuário atual para a aula em destaque (0 se não houver registro) como dado inicial do componente Alpine.
2. `x-init` cria `new Vimeo.Player(iframeEl)`.
3. No evento `loaded` do SDK: se `watched_seconds > 0` e a posição estiver a mais de 5s do fim (`player.getDuration()`), chama `player.setCurrentTime(watched_seconds)`. Evita "retomar" bem no fim, o que pareceria o vídeo já ter acabado.

**Durante a reprodução (sem chamadas ao servidor):**
4. No `timeupdate` (evento só local, sem custo de rede), calcula `percent = currentTime / duration`. Ao cruzar `percent >= 0.9` pela primeira vez nesta sessão de player, chama `$wire.markCompleted(lessonId)` uma única vez (flag local no componente Alpine evita repetir).

**Eventos que persistem posição:**
5. No `pause` do player: chama `$wire.updateProgress(lessonId, Math.floor(currentTime))`.
6. No `ended` do player: chama `$wire.updateProgress(lessonId, Math.floor(currentTime))` (cobre o caso de assistir até o fim sem pausar manualmente).

**Troca de aula:**
7. Clique em outra aula do carrossel continua chamando `watchLesson()` (já existe, sem mudança) — que seta `status = 'watching'` para a nova aula. A troca do `wire:key` do hero destrói o player antigo e cria um novo para a aula recém-selecionada, reiniciando o ciclo acima.

## 5. Regras de negócio

- `updateProgress` sempre atualiza `watched_seconds` + `last_watched_at`, mas nunca reverte `status = 'completed'` de volta para `watching`.
- `markCompleted` seta `status = 'completed'` e não é chamado mais de uma vez por sessão de player (guard local no Alpine); chamadas subsequentes de `updateProgress` (por `pause`/`ended`) continuam atualizando `watched_seconds` normalmente mesmo após `completed`.
- As duas actions operam sempre sobre `Auth::id()` do lado do servidor — o client só manda `lessonId` e, quando aplicável, `seconds`; nunca `user_id`.
- Limiar de conclusão: **90%** de `player.getDuration()` — não do `duration_seconds` salvo no banco (esse campo é só exibição no card, pode estar desatualizado/impreciso).

## 6. Edge cases

- Troca rápida de aula antes do player anterior terminar de carregar: o SDK do Vimeo enfileira/ignora comandos pendentes sem quebrar; a `wire:key` já garante que o componente antigo é descartado.
- Falha de rede numa chamada `$wire.updateProgress`/`markCompleted`: falha silenciosa, sem retry — perda aceitável (mesmo trade-off da seção 2).
- Fechar a aba/trocar de aula no meio da reprodução sem pausar antes: posição não é salva daquele trecho (trade-off assumido na seção 2).

## 7. Testes

- Feature tests em `tests/Feature/Livewire/Membros/DashboardTest.php` (arquivo já existe) cobrindo os métodos novos `updateProgress`/`markCompleted`:
  - `updateProgress` upserta `watched_seconds`/`last_watched_at` corretamente para o usuário autenticado.
  - `updateProgress` não rebaixa `status = 'completed'` para `watching`.
  - `markCompleted` seta `status = 'completed'`.
  - Ambos escopados ao usuário autenticado (não afetam `lesson_progress` de outro usuário).
- Sem suite de testes JS (o projeto não tem uma) — integração do SDK validada manualmente via `npm run dev` no navegador.

## 8. Fora de escopo

- YouTube (tracking/retomada só para Vimeo, por decisão explícita).
- Fila/retry de progresso perdido por queda de rede ou por fechar a aba sem pausar.
- Analytics agregado de watch-time.
- Salvar posição em intervalo periódico (polling) — decisão explícita da seção 2.
