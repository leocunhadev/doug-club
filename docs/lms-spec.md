# Spec — Área de Membros (LMS) "Estabilidade Não Existe"

## 1. Contexto

Recriar a área de membros mostrada no design de referência: um LMS simples de conteúdo em vídeo, com um player em destaque (última aula assistida) e, abaixo, os cursos/módulos organizados em carrosséis horizontais de aulas.

Projeto é um Laravel 13 novo (skeleton), Tailwind 4 + Vite, SQLite em dev. Ainda não há auth, models de domínio ou views além do `welcome.blade.php`.

## 2. Stack e decisões

- **Backend/rotas:** Laravel 13, Blade.
- **Interatividade:** Livewire 3 + Alpine.js para carrosséis, troca de vídeo em destaque e marcação de progresso (sem precisar de API JSON separada).
- **Auth:** instalar **Laravel Breeze — stack "Blade with Alpine" com Livewire** (`php artisan breeze:install livewire`). Cobre login/logout/reset de senha. **Sem self-registration pública** — usuários são criados por seeding/admin (compra é liberada externamente, ex. webhook de checkout — fora de escopo desta spec).
- **Vídeo:** embed externo (Panda Video / Vimeo / YouTube). O banco guarda só `video_provider` + `video_id`/`video_url`; nenhum upload/streaming próprio.
- **Banco:** SQLite em dev (já configurado), sem mudanças necessárias para produção nesta fase.

## 3. Modelo de dados

```
users
  id, name, email, password, ...  (Breeze default)

courses                    -- "Módulo 4: Modelos de Negócio" / "Curso 3: ..."
  id
  title            string   -- "Modelos de Negócio"
  label            string   -- "Módulo 4" | "Curso 3" (texto livre, exibido antes do title)
  description      text nullable  -- subtítulo abaixo do título da seção
  position         integer  -- ordena as seções na home (desc = mais recente primeiro)
  created_at / updated_at

lessons                     -- "aulas"
  id
  course_id        FK -> courses
  number           integer  -- "AULA 05" (exibido, não necessariamente = id)
  title            string   -- "Estabilidade Não Existe - Modelo de Negócios - Aula 05"
  duration_seconds integer nullable
  video_provider   enum('vimeo','youtube','panda')
  video_id         string   -- id/hash do embed
  thumbnail_path   string nullable  -- poster do card; fallback = frame do provider
  published_at     date     -- "17/07/2026" exibido no card
  position         integer  -- ordena dentro do curso (desc = mais recente primeiro)

lesson_materials             -- "Materiais de aula"
  id
  lesson_id        FK -> lessons
  title            string
  file_url         string    -- link externo (drive/S3) ou path em storage

lesson_progress              -- estado por usuário
  id
  user_id          FK -> users
  lesson_id        FK -> lessons
  status           enum('not_started','watching','completed')
  watched_seconds  integer default 0   -- posição para retomar o player
  last_watched_at  timestamp nullable
  unique(user_id, lesson_id)
```

Relacionamentos: `Course hasMany Lesson`, `Lesson hasMany LessonMaterial`, `Lesson hasMany LessonProgress`, `User hasMany LessonProgress`.

"Boas Vindas" é apenas um `Course` com `position` mais alto (aparece antes dos módulos) e um único `Lesson`, sem tratamento especial no código.

## 4. Rotas

| Rota | Método | Middleware | Descrição |
|---|---|---|---|
| `/login`, `/logout`, `/forgot-password`, `/reset-password/{token}` | Breeze default | `guest`/`auth` | Autenticação |
| `/membros` | GET | `auth` | Página única do dashboard (imagem de referência) |
| `/membros/aulas/{lesson}/assistir` | POST (Livewire action) | `auth` | Marca a aula clicada como `watching`, atualiza player em destaque |
| `/membros/materiais/{lesson}` | GET | `auth` | Lista/baixa materiais da aula (pode ser modal Livewire em vez de rota própria) |

`/` redireciona para `/membros` se autenticado, senão para `/login`.

## 5. Composição da UI (`resources/views/livewire/membros/dashboard.blade.php`)

Estrutura de cima para baixo, cada bloco como componente Livewire ou Blade component:

1. **Header** (`<x-app-header>`) — logo à esquerda, avatar com iniciais do usuário à direita (dropdown: sair).
2. **Hero** (`<livewire:membros.hero-player>`)
   - Título fixo "Sua central de conteúdos" + texto descritivo (conteúdo estático, não vem do banco).
   - Player de vídeo grande = aula atual do usuário (última com `status = watching`, ou a primeira aula do curso mais recente se não houver progresso).
   - Botão "Materiais de aula" abre lista de `lesson_materials` da aula em destaque.
3. **Carrosséis de curso** — um por `Course`, na ordem de `position` desc:
   - Cabeçalho da seção: `label + ": " + title` (h2) e `description` (subtítulo), só renderiza subtítulo se existir.
   - `<livewire:membros.lesson-carousel :course="$course">`: track horizontal (`overflow-x-auto`/`scroll-snap` + Alpine para os botões `‹ ›`), um `<x-lesson-card>` por `Lesson` do curso, ordenados por `position` desc.
4. **Footer** — links "Política de Privacidade" / "Sobre", texto de copyright, botão flutuante fixo do WhatsApp (`fixed bottom-4 right-4`).

### `<x-lesson-card>`

Estados/elementos do card (ver imagem):
- Thumbnail com overlay em gradiente escuro → laranja diagonal.
- Selo pequeno do curso ("CURSO" + nome) no topo do card.
- "AULA {number}" grande.
- Duração no canto (ex. `48:43`, `1h 07min`) — formatar `duration_seconds`.
- Badge "ASSISTINDO" (canto superior direito, pill laranja) **somente** se `lesson_progress.status === 'watching'` para o usuário logado.
- Data de publicação (`published_at` formatada `d/m/Y`) + título completo abaixo da thumbnail.
- Clique no card → aciona a action Livewire que atualiza o player do Hero e o `lesson_progress` (sem navegar de página).

### Carrossel

- Setas `‹`/`›` só aparecem (ou só ficam ativas) quando há conteúdo a rolar naquele sentido; usar Alpine para togglar via `scrollLeft`/`scrollWidth`.
- Mobile: scroll por swipe nativo, setas ocultas (`hidden md:flex`).

## 6. Regras de negócio

- **Aula em destaque no Hero** = a aula com `lesson_progress.last_watched_at` mais recente para o usuário; se o usuário nunca assistiu nada, cai na primeira aula do curso de maior `position` (ex. "Boas Vindas").
- **Badge "ASSISTINDO"** aparece em exatamente um card por vez (o mesmo que está no Hero).
- Trocar de aula no carrossel → `upsert` em `lesson_progress` (`status = watching`, `last_watched_at = now()`), sem marcar `completed` automaticamente (isso ficaria a cargo de um evento futuro do player ao chegar perto do fim — fora de escopo agora).
- Cursos e aulas sem nenhum registro de `lesson_progress` são tratados como "não iniciado" — não exibem badge.

## 7. Design tokens

- **Fundo:** quase preto (`#0a0a0b`), cards em cinza-azulado bem escuro (`#12141a` / `slate-950` com borda `slate-800/60`).
- **Acento:** gradiente laranja→vermelho (`from-orange-500 to-red-600`) usado no logo, badges ("ASSISTINDO"), botões primários e glow diagonal nas thumbnails.
- **Tipografia:** títulos em branco/bold, texto secundário em `gray-400`, labels de seção ("CURSO", "AULA") em caixa alta, tracking largo, tamanho pequeno.
- **Cards:** `rounded-xl`, sombra sutil, hover com leve *scale*/brightness.
- **Espaçamento entre seções:** generoso (`space-y-16`/`space-y-20`), títulos de seção com `mb-2` entre título e subtítulo.

## 8. Fora de escopo (backlog)

- Painel admin para cadastrar cursos/aulas/materiais (candidato natural: Filament, já que é Laravel — avaliar depois).
- Webhook de plataforma de pagamento para criar/liberar `User`.
- Marcar aula como `completed` automaticamente via evento do player (% assistido).
- Busca/filtro de aulas, página de materiais dedicada (hoje: modal/lista simples).
