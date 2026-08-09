# Spec — Auto-preenchimento de duration (Vimeo) + bloqueio de edição

## 1. Contexto

No Filament (`app/Filament/Resources/Lessons/Schemas/LessonForm.php`), o campo `duration_seconds` é um `TextInput` livre (`mm:ss` ou `h:mm:ss`) que o admin digita manualmente, tanto para aulas Vimeo quanto YouTube. Isso é propenso a erro de digitação e diverge do valor real do vídeo.

Objetivo: quando o provider for **Vimeo**, buscar a duração automaticamente a partir do `video_id` e bloquear a edição manual do campo. YouTube fica fora de escopo (fica como está hoje) — o oEmbed do YouTube não retorna duração, e buscar via YouTube Data API v3 exigiria configurar uma API key nova, o que é desproporcional ao pedido atual.

## 2. Decisões

- **Provider:** só Vimeo. YouTube continua 100% manual.
- **Gatilho:** automático, ao sair do campo `video_id` (blur) quando o provider é Vimeo — sem botão adicional.
- **Fallback de erro:** se a busca falhar (ID inválido, vídeo privado, erro de rede/timeout), o campo `duration_seconds` é **liberado para edição manual** (não fica travado sem valor) e uma notificação de aviso é exibida.
- **Fonte dos dados:** endpoint público do Vimeo oEmbed, sem necessidade de API key/token:
  `https://vimeo.com/api/oembed.json?url=https://vimeo.com/{video_id}` → campo `duration` da resposta, em segundos.

## 3. Arquitetura

### `App\Services\Vimeo\VimeoOembedClient`

Novo client dedicado, único responsável por falar com a API do Vimeo:

```php
public function getDurationInSeconds(string $videoId): ?int
```

- Faz `Http::timeout(5)->get('https://vimeo.com/api/oembed.json', ['url' => "https://vimeo.com/{$videoId}"])`.
- Retorna `null` para **qualquer** condição de falha: status HTTP != 200, exceção de conexão/timeout, JSON sem a chave `duration`. Nunca deixa uma exceção vazar para quem chama — a responsabilidade de decidir o que fazer com "não deu" é do form, não do client.
- Sem cache, sem retry — chamada síncrona simples, disparada só na interação do admin (blur), não em toda renderização.

### `LessonForm` — novos elementos do schema

- **Campo oculto `duration_locked`** (não persistido — `dehydrated(false)`): flag de UI que controla se `duration_seconds` está bloqueado.
  - Default: `fn (?Lesson $record) => $record?->video_provider === 'vimeo'`. Isso faz uma edição de aula Vimeo já existente abrir **já bloqueada** (assume-se que a duração salva veio de um fetch anterior válido), sem precisar refazer a chamada HTTP a cada abertura do form.
- **`Select::make('video_provider')->live()`** — precisa ser reativo para que trocar o provider (ex.: de youtube para vimeo, mantendo um `video_id` já preenchido) também dispare a sincronização.
- **`TextInput::make('video_id')->live(onBlur: true)`** — reativo só no blur (não a cada tecla), para não disparar uma request HTTP por caractere digitado.
- Ambos os campos chamam o mesmo método estático `LessonForm::syncVimeoDuration(Get $get, Set $set)`:
  1. Se `video_provider !== 'vimeo'` → `$set('duration_locked', false)` e retorna. (Restaura o comportamento manual atual para YouTube, inclusive se o admin trocar de vimeo para youtube depois de já ter um valor bloqueado.)
  2. Se `video_provider === 'vimeo'` e `video_id` está em branco → retorna sem alterar nada (não mexe em `duration_seconds` nem em `duration_locked`).
  3. Se `video_provider === 'vimeo'` e `video_id` preenchido → chama `VimeoOembedClient::getDurationInSeconds($videoId)`:
     - **Sucesso** (`int` retornado): `$set('duration_seconds', LessonForm::formatDuration($seconds))` (reaproveita o formatter existente, mantendo o mesmo formato `mm:ss`/`h:mm:ss` já usado por `formatStateUsing`/`dehydrateStateUsing`) e `$set('duration_locked', true)`.
     - **Falha** (`null`): `$set('duration_locked', false)` e dispara `Notification::make()->warning()->title('Não foi possível obter a duração do Vimeo automaticamente')->body('Preencha a duração manualmente.')->send()`. **Não** apaga um valor de `duration_seconds` que já existisse no campo.
- **`TextInput::make('duration_seconds')`** ganha `->disabled(fn (Get $get) => (bool) $get('duration_locked'))`. O resto do campo (regex, `formatStateUsing`, `dehydrateStateUsing`) não muda.

### Efeito prático (resumo)

| Cenário | `duration_seconds` |
|---|---|
| Vimeo + `video_id` válido | preenchido automaticamente e bloqueado |
| Vimeo + `video_id` inválido/vídeo privado/erro de rede | liberado para digitação manual + aviso |
| Vimeo, `video_id` em branco | inalterado (sem fetch) |
| YouTube (qualquer estado) | sempre editável, sem mudanças de comportamento |
| Editar aula Vimeo já existente, sem tocar em `video_id` | abre bloqueada com o valor salvo, sem refazer fetch |
| Trocar provider de YouTube → Vimeo com `video_id` já preenchido | dispara o fetch imediatamente (via `live()` do Select) |

## 4. Testes

- **Unit/feature do `VimeoOembedClient`:** `Http::fake()` simulando resposta 200 com `duration`, resposta sem `duration`, status de erro (404/500) e timeout/exceção de conexão — todos devem retornar `null` exceto o caso de sucesso.
- **Feature (Filament/Livewire) do `LessonForm`:**
  - Vimeo + `video_id` válido (Http fake de sucesso) → após simular o blur, `duration_seconds` reflete o valor buscado e o campo está `disabled`.
  - Vimeo + `video_id` inválido (Http fake de falha) → campo permanece habilitado, sem valor forçado, e uma notificação de warning é registrada.
  - YouTube → nenhuma chamada HTTP é feita (`Http::fake()` sem expectativa de request) e o campo continua editável como hoje.
  - Editar uma aula Vimeo existente sem alterar `video_id` → form carrega com o campo já `disabled`, sem nenhuma request HTTP disparada no mount.
- Os testes já existentes em `tests/Feature/Admin/LessonResourceTest.php` que usam `video_provider = 'youtube'` continuam passando sem alteração (fora do escopo desta mudança).

## 5. Fora de escopo

- Auto-preenchimento de duração para YouTube (exigiria API key própria — backlog futuro se necessário).
- Cache do resultado do oEmbed (baixo volume de cadastro de aulas; não justifica a complexidade agora).
- Botão manual de "recarregar duração" — o próprio blur no `video_id` já cobre o caso de precisar refazer o fetch.
