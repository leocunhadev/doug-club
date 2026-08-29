# Biblioteca de aulas (item #2 do lista-spec) — página real /membros/aulas

Recria, no app real, a página `/prototype/aulas` (referência: `resources/views/prototype/aulas.blade.php`
+ `config/prototype.php`), usando os `Course`/`Lesson` reais do banco em vez do array estático do
protótipo. Move a listagem de aulas — hoje embutida na Início como carrosséis de curso — para essa
página dedicada, e libera a aba "Aulas" (hoje trancada) na navegação por persona.

## 1. Contexto

Confirmado com o usuário:

- É a página real (`/membros/aulas`), não uma variação do `/prototype/*`.
- O protótipo filtra aula por **categoria** (Encontros/Convidados/Frameworks) e por **tier**
  (start/club) — nenhum dos dois campos existe hoje no `Lesson` real. Ambos entram nesta spec.
- A Início perde os carrosséis de curso — fica só com "Continuar assistindo" + os dois cards que já
  existem (Novidade/Sessão 1:1, Atalhos), igual ao `prototype/home.blade.php`.

O conteúdo real hoje seedado (`LmsSeeder`) é dado de demonstração genérico ("Estabilidade Não
Existe", cursos "Vendas"/"Influência"/etc.) — não tem relação com a marca DO.ing/Douglas Oliveira
usada no resto do app. Isso é esperado: a taxonomia nova (categoria/tier) é sobre a *estrutura* da
biblioteca, não sobre o conteúdo de demonstração em si. `LmsSeeder` ganha valores de categoria/tier
plausíveis pros registros que já cria, só pra a página nova ter o que filtrar em dev.

## 2. Modelo de dados

Migration `add_category_and_tier_to_lessons_table`:

```
lessons
  category   enum('Encontros','Convidados','Frameworks')   default 'Encontros'   not null
  tier       enum('start','club')                          default 'start'       not null
```

Ambos os campos entram no `$fillable`/`#[Fillable]` do `Lesson`. `category` e `tier` só existem no
`Lesson` (não no `Course`) porque o protótipo mostra tier por aula individual, não por módulo — o
mesmo módulo pode ter aulas start e club misturadas (não é o caso hoje no seed, mas o campo já nasce
correto pro dia em que for).

`Lesson` ganha um helper de acesso, no mesmo padrão de `User::hasClubAccess()`:

```php
public function isAvailableFor(User $user): bool
{
    return $this->tier === 'start' || $user->hasClubAccess();
}
```

## 3. Reuso: trait + componente compartilhado

A Início e a nova Aulas precisam do mesmo player (iframe + progresso + materiais) e da mesma lógica
de "marcar aula assistida". Em vez de duplicar:

### `App\Livewire\Concerns\TracksLessonProgress` (trait)

Move de `Dashboard` pra essa trait, sem mudança de comportamento: `$featuredLessonId`, `mount()`
(via `DetermineFeaturedLesson`), `watchLesson()`, `updateProgress()`, `markCompleted()`,
`featuredLesson()` (computed), `featuredProgress()` (computed). `Dashboard` e a nova `Aulas` usam a
trait; nenhuma delas precisa de `mount()` próprio além do que a trait já faz.

### `<x-lesson-player :lesson="..." :progress="..." />` (componente Blade)

Extrai o bloco de vídeo + dropdown de materiais que hoje vive inline em `dashboard.blade.php`
(linhas 31-92 da versão atual: `wire:key`, `x-data="vimeoProgress(...)"`, iframe, dropdown de
materiais) pra um componente próprio, sem mudança visual. Recebe `$lesson` (nullable) e mostra
"Nenhuma aula disponível ainda." quando nulo — mesmo comportamento de hoje. Usado pela Início (dentro
do bloco "Continuar assistindo") e pela Aulas (no player grande do topo).

## 4. Página `/membros/aulas`

### Rota

```php
Route::get('membros/aulas', Aulas::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.aulas');
```

### `App\Livewire\Membros\Aulas`

```php
class Aulas extends Component
{
    use ComputesUserInitials, TracksLessonProgress;

    public string $category = 'Tudo';

    public function selectCategory(string $category): void
    {
        $this->category = $category;
    }

    #[Computed]
    public function lessons()
    {
        return Lesson::query()->with('course')
            ->when(! Auth::user()->hasClubAccess(), fn ($q) => $q->where('tier', 'start'))
            ->when($this->category !== 'Tudo', fn ($q) => $q->where('category', $this->category))
            ->orderByDesc('published_at')
            ->orderByDesc('position')
            ->get();
    }

    #[Computed]
    public function totalCount(): int
    {
        return Lesson::query()
            ->when(! Auth::user()->hasClubAccess(), fn ($q) => $q->where('tier', 'start'))
            ->count();
    }

    public function render()
    {
        return view('livewire.membros.aulas');
    }
}
```

`lessons()` filtra por tier E categoria (o que aparece na grade); `totalCount()` filtra só por tier
(o que vai no "N aulas na sua biblioteca" do player-bar) — mesma regra do `visibleAulas()` vs.
`total` do protótipo original (`doingclub.html`), onde o contador ignora o filtro de categoria ativo.

Categorias fixas: `['Tudo', 'Encontros', 'Convidados', 'Frameworks']` (mesmas do protótipo,
hardcoded na view — não é dado de banco).

### View (`resources/views/livewire/membros/aulas.blade.php`)

Estrutura, de cima pra baixo:

1. `x-membros.header`
2. Cabeçalho: "Biblioteca de aulas" (h1, mesmo tratamento Syne/800/tracking do H1 da Início) +
   subtítulo "Todos os encontros gravados, aulas de convidados e frameworks em vídeo..."
3. `<x-lesson-player :lesson="$this->featuredLesson" :progress="$this->featuredProgress" />` — o
   player grande do topo, controlado pelas mesmas ações da trait.
4. Barra "Assistindo agora: **{título da aula em destaque}** · N aulas na sua biblioteca" — abaixo
   do player, mesmo texto/formato do protótipo (`.player-bar`).
5. Filtros de categoria: pills `Tudo | Encontros | Convidados | Frameworks`, `wire:click="selectCategory('...')"`,
   estado ativo com `bg-black text-white` (mesmo padrão visual das abas do header).
6. Grid de aulas (`<x-aula-card>` por `Lesson` de `$this->lessons`).

### `<x-aula-card :lesson="..." :watching="..." />`

Componente novo (não reaproveita `<x-lesson-card>`, que tem outro desenho — selo de curso, "Aula NN"
grande, chip de duração). Estrutura e classes portadas do `.aula`/`.thumb`/`.n`/`.p`/`.body` do
protótipo (seção 5): número grande contornado no canto superior direito da thumb, círculo de play no
canto inferior esquerdo, título + metadados abaixo (com "· Exclusivo CLUB" quando `tier === 'club'`).
`wire:click="watchLesson({{ $lesson->id }})"` — mesma ação da trait, atualiza o player do topo sem
reload.

## 5. CSS portado do protótipo

`resources/css/app.css` ganha as classes `.aula`, `.aula .thumb`, `.aula .thumb .n`, `.aula .thumb .p`,
`.aula .body`, `.aula-filters` — copiadas de `resources/views/prototype/partials/_styles.blade.php`
(seção `PLAYER / AULAS`), com `var(--orange)`/`var(--black)`/etc. trocados por `theme('colors.brand')`/
`theme('colors.black')`, mesmo padrão já usado pra `.marquee`/`.wordmark`/`.planswitch`.

**Não** portamos `.player`/`.playbtn`/`.fake-video`/`.progress`/`.player-bar` (fundo escuro +
"clique pra revelar o vídeo falso") — esse é o mecanismo de demonstração do protótipo pra simular um
player sem vídeo de verdade. O app real já tem vídeo de verdade (iframe Vimeo/YouTube) desde o
início do projeto; `<x-lesson-player>` mantém o desenho atual (`rounded-2xl border-sand bg-card`),
que é estritamente melhor que a versão falsa do protótipo pro mesmo propósito. `.player-bar` como
*texto* (não a classe de fundo escuro) é replicado como um parágrafo simples abaixo do player.

## 6. Início (dashboard)

`dashboard.blade.php` perde o bloco `@foreach ($this->courses as $course) ... @endforeach` (linhas
138-192 da versão atual) inteiro. `Dashboard.php` perde o computed `courses()` e o import de
`App\Models\Course` (nada mais os usa). O bloco "Continuar assistindo" passa a usar
`<x-lesson-player>` no lugar do markup inline (via a extração da seção 3), sem mudar o que é exibido.

`newestLesson()` (card "Novidade na biblioteca", só Start) passa a filtrar `where('tier', 'start')`
— não faz sentido recomendar pro Start uma aula que ele não pode assistir.

## 7. Navegação

`App\Support\PersonaNavigation::tabs()`: o item `Aulas` (`route: 'membros.aulas'`) vira
`available: true` nas listas `start` e `club`. Isso automaticamente destrava a aba no header e o
link "Biblioteca de aulas" no card Atalhos da Início (ambos já leem `available` de lá — nenhuma
outra mudança necessária nesses dois lugares).

## 8. Testes

- `Tests\Unit\LessonTest` (ou extensão de um existente): `isAvailableFor()` — `true` pra tier
  `start` com qualquer usuário; `true`/`false` pra tier `club` conforme `hasClubAccess()`.
- `Tests\Feature\Livewire\Membros\AulasTest` (novo): guest redireciona pro login; grid mostra só
  aulas `start` pra usuário Start (mesmo com aulas `club` no banco); grid mostra tudo pra CLUB;
  filtro de categoria funciona (`selectCategory` + assert grid); `totalCount` ignora o filtro de
  categoria mas respeita o tier; `watchLesson` num card atualiza o player do topo (mesmo padrão dos
  testes já existentes em `DashboardTest`); "· Exclusivo CLUB" aparece só nos cards `tier=club`.
- `Tests\Feature\Livewire\Membros\DashboardTest`: remove os testes que dependiam dos carrosséis de
  curso (`test_dashboard_renders_featured_lesson_embed_and_materials` continua válido pro player,
  mas os que verificam carrossel/curso saem ou são adaptados pra continuarem cobrindo só o hero);
  `test_watching_badge_appears_on_exactly_the_featured_lesson_card` deixa de fazer sentido sem
  carrossel — sai (o equivalente nasce em `AulasTest`, já que é lá que a lista de aulas aparece
  agora).
- Teste de integração leve confirmando que `LmsSeeder` roda sem erro com os novos campos
  obrigatórios (já coberto indiretamente por qualquer teste que dependa do seeder, se houver; senão,
  rodar `php artisan migrate:fresh --seed` manualmente como verificação, não como teste automatizado).

## 9. Fora de escopo

- Frameworks DO (aba separada, item também mencionado no protótipo mas não pedido nesta spec).
- Qualquer UI de admin/Filament pra editar `category`/`tier` de uma aula — por enquanto só
  `LmsSeeder`/tinker/seed definem esses campos, mesmo padrão de `tier` no `User` (item #1).
- Migrar o conteúdo real ("Estabilidade Não Existe") pra taxonomia DO.ing — fora do escopo técnico
  desta spec, é decisão de conteúdo/negócio.
