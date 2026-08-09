# Retomar aula (Vimeo) de onde parou e marcar como concluída

**Spec:** [2026-08-09-vimeo-watch-progress-design.md](../specs/2026-08-09-vimeo-watch-progress-design.md)

## Motivação

O player em destaque do dashboard de membros hoje é um `<iframe>` estático — não lê nem retoma o tempo do vídeo. O schema de `lesson_progress` já tem `watched_seconds`/`last_watched_at` pensados pra isso (ver [lms-spec.md](../../lms-spec.md)), mas nada os preenche hoje.

## Escopo

- Integrar o Vimeo Player SDK (`@vimeo/player`) no player em destaque.
- Ao abrir uma aula com progresso salvo, retomar automaticamente do `watched_seconds` salvo (exceto se estiver a menos de 5s do fim).
- Salvar a posição assistida nos eventos `pause` e `ended` do player — **sem polling periódico**, pra não gerar uma chamada ao servidor a cada N segundos enquanto a pessoa assiste.
- Marcar a aula como `status = 'completed'` automaticamente ao cruzar 90% de `player.getDuration()`, uma única vez por sessão de player.

## Fora de escopo

- YouTube (tracking/retomada só para Vimeo por enquanto).
- Retry/fila de progresso perdido por queda de rede ou fechamento de aba sem pausar antes.
- Analytics agregado de watch-time.

## Critérios de aceite

- [ ] Abrir uma aula com `watched_seconds` salvo retoma o player automaticamente na posição certa (exceto perto do fim).
- [ ] Pausar o player grava `watched_seconds`/`last_watched_at` para o usuário autenticado.
- [ ] Terminar o vídeo (`ended`) grava a posição final.
- [ ] Assistir além de 90% da duração marca `lesson_progress.status = 'completed'`, uma única vez.
- [ ] `updateProgress` nunca reverte `status = 'completed'` de volta para `watching`.
- [ ] Nenhuma chamada ao servidor ocorre em intervalo fixo (polling) enquanto o vídeo só está tocando sem pausar.
- [ ] Trocar de aula no carrossel reinicializa o player corretamente para a nova aula (via `wire:key`).
- [ ] Testes de feature cobrindo as novas actions/métodos do Livewire (`updateProgress`, `markCompleted`), escopados ao usuário autenticado.
