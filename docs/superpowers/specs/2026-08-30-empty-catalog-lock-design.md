# Cadeado de catálogo vazio + Página de materiais por aula (issue #26)

Fecha a issue #26 e estabelece um padrão cross-cutting pedido pelo usuário: páginas de catálogo
(conteúdo publicado pelo mentor/admin, igual para todo mundo) mostram um estado de "em breve" de
página inteira pra usuários comuns quando estão genuinamente vazias, em vez do texto de vazio de
sempre — reservado só pra quem pode resolver a ausência de conteúdo (admin, mentor).

## 1. Contexto

Confirmado com o usuário, depois de duas rodadas de clarificação:

- **Escopo do cadeado: só páginas de catálogo** — Aulas, Frameworks e a nova página de Materiais.
  Cofre, Pessoas e Dossiês ficam de fora: o vazio ali é pessoal/relacional (este mentorado
  específico ainda não tem documento/nota, ou não há outro membro CLUB ainda), não "conteúdo ainda
  não publicado" — continuam com a mensagem de vazio que já têm.
- **Quem vê o real mesmo vazio**: `is_admin` OU `isMentor()` — são as pessoas que resolveriam a
  ausência de conteúdo publicando algo. Confirmado explicitamente: o mentor sempre vê o conteúdo
  real nas próprias páginas, não só quem tem a flag `is_admin`.
- **"Catálogo vazio" (trava) vs. "filtro sem resultado" (não trava)**: em Aulas, busca ou categoria
  sem resultado continua com a mensagem atual — o cadeado só aparece quando não existe *nenhuma*
  aula visível pro tier do usuário, período. Em Materiais, o cadeado usa a condição agregada do
  sistema inteiro (`LessonMaterial::count() === 0`, a mesma que já bloqueava a #14) — uma vez que
  qualquer material exista em qualquer aula, uma aula específica sem material mostra a mensagem
  normal de vazio, não o cadeado (é um estado por-aula esperado, não "não está pronto").
- **Visual**: estado de página inteira, mesmo espírito do `MentorPlaceholder` já existente (que
  literalmente já é uma "página em breve" estática) — cabeçalho da página some, some substituído por
  um bloco centralizado com 🔒 + título + mensagem.

## 2. Componentes compartilhados

### 2.1 `App\Livewire\Concerns\ComputesCatalogAccess` (trait)

Mesmo padrão de `ComputesUserInitials` — um `#[Computed]` reutilizável:

```php
namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

trait ComputesCatalogAccess
{
    #[Computed]
    public function canSeeEmptyCatalog(): bool
    {
        return Auth::user()->is_admin || Auth::user()->isMentor();
    }
}
```

### 2.2 `<x-catalog-empty-lock>` (Blade component)

`resources/views/components/catalog-empty-lock.blade.php`:

```blade
@props(['title', 'message'])

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-24 text-center">
    <div class="text-4xl mb-4" aria-hidden="true">🔒</div>
    <h1 class="text-2xl font-bold font-display">{{ $title }}</h1>
    <p class="mt-3 text-stone">{{ $message }}</p>
</div>
```

Mesma estrutura visual do `mentor-placeholder.blade.php` (`max-w-3xl mx-auto px-4 sm:px-6 lg:px-8
py-24 text-center`, `text-2xl font-bold font-display`, `mt-3 text-stone`), só acrescentando o ícone
🔒 pedido pelo usuário.

### 2.3 Uso nas páginas

Cada página de catálogo aplica o mesmo padrão na view: quando `catalogIsEmpty && !
$this->canSeeEmptyCatalog`, renderiza só `<x-membros.header>` + `<x-catalog-empty-lock>` +
`<x-membros.footer>` — a página normal (título, filtros, grid) some inteira, igual ao
`MentorPlaceholder`.

- **Aulas**: `catalogIsEmpty` = `$this->totalCount === 0` (já existe, já é escopado por tier —
  nenhum computed novo necessário).
- **Frameworks**: `catalogIsEmpty` = `$this->frameworks->isEmpty()` (`frameworks()` não tem filtro
  de busca/categoria, então já é exatamente "o catálogo inteiro está vazio" — nenhum computed novo
  necessário).
- **Materiais** (nova, seção 3): `catalogIsEmpty` = novo computed `LessonMaterial::query()->count()
  === 0` (agregado do sistema inteiro, não da aula específica).

## 3. Página de materiais por aula

```php
Route::get('membros/aulas/{lesson}/materiais', AulaMateriais::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.aulas.materiais');
```

Mesmo middleware de `membros.aulas` (qualquer tier logado) — o gate de disponibilidade específico
da aula acontece dentro do componente via `Lesson::isAvailableFor()`.

### `App\Livewire\Membros\AulaMateriais`

```php
class AulaMateriais extends Component
{
    use ComputesUserInitials;
    use ComputesCatalogAccess;

    public Lesson $lesson;

    public function mount(Lesson $lesson): void
    {
        abort_unless($lesson->isAvailableFor(Auth::user()), 404);

        $this->lesson = $lesson;
    }

    #[Computed]
    public function materials(): Collection
    {
        return $this->lesson->materials()->orderBy('id')->get();
    }

    #[Computed]
    public function catalogIsEmpty(): bool
    {
        return LessonMaterial::query()->count() === 0;
    }

    public function render()
    {
        return view('livewire.membros.aula-materiais');
    }
}
```

`mount(Lesson $lesson)` usa o binding implícito de rota do Laravel (já suportado nativamente por
componentes Livewire de página inteira, mesmo mecanismo de um controller comum) — 404 automático se
o id na URL não existir, e `abort_unless` cobre o caso de a aula existir mas não estar disponível
pro tier do usuário (mesma checagem já usada em `LessonMaterialDownloadController` e
`lesson-player.blade.php`).

### View

Reaproveita as classes `.doc-row`/`.doc-ic` já portadas no Cofre (#22) — mesma forma visual (lista
de itens com ícone curto + título + ação), então não precisa de CSS nova:

1. `x-membros.header`
2. Se `catalogIsEmpty && ! $this->canSeeEmptyCatalog`: `<x-catalog-empty-lock title="Os materiais de
   aula estão sendo preparados." message="Em breve o Douglas vai adicionar os primeiros arquivos por
   aqui." />`
3. Senão: link "← Voltar pra Aulas" (`route('membros.aulas')`), título "Materiais · {{
   $this->lesson->title }}", lista `.doc-row` de `$this->materials` (ícone via `icon_label`, seção
   3.1; título; botão "Baixar" pra `membros.materials.download` quando `hasUploadedFile()`, "Abrir"
   em nova aba quando é link externo), com `@forelse`/`@empty` mostrando "Nenhum material para esta
   aula ainda." quando a aula específica não tem nada (isso SEMPRE pode acontecer mesmo com o
   catálogo geral não-vazio — é o estado pessoal-por-aula, não o cadeado).

### 3.1 `LessonMaterial::iconLabel()` (novo accessor)

Mesmo mapeamento exato já usado em `VaultDocument::iconLabel()` (#22), copiado porque os dois
models compartilham a mesma forma (upload local ou link externo):

```php
protected function iconLabel(): Attribute
{
    return Attribute::get(function () {
        $path = $this->hasUploadedFile() ? $this->file_path : $this->file_url;
        $extension = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));

        return match (true) {
            $extension === 'pdf' => 'PDF',
            in_array($extension, ['mp4', 'mov', 'webm'], true) => 'VÍDEO',
            in_array($extension, ['xlsx', 'xls'], true) => 'XLSX',
            in_array($extension, ['doc', 'docx'], true) => 'DOC',
            ! $this->hasUploadedFile() && filled($this->file_url) => 'LINK',
            default => 'ARQUIVO',
        };
    });
}
```

## 4. Retrofit: Aulas e Frameworks

- `App\Livewire\Membros\Aulas`: adiciona `use ComputesCatalogAccess;`.
- `App\Livewire\Membros\Frameworks`: adiciona `use ComputesCatalogAccess;`.
- `aulas.blade.php` / `frameworks.blade.php`: envolve todo o conteúdo atual (menos
  `x-membros.header`/`x-membros.footer`) num `@if ($this->totalCount > 0 ||
  $this->canSeeEmptyCatalog)` (Aulas) / `@if ($this->frameworks->isNotEmpty() ||
  $this->canSeeEmptyCatalog)` (Frameworks) ... `@else` ... `<x-catalog-empty-lock ... />` ...
  `@endif`.
  - Aulas: título "Sua biblioteca de aulas está sendo preparada.", mensagem "Em breve os primeiros
    conteúdos chegam por aqui."
  - Frameworks: título "Os frameworks estão sendo preparados.", mensagem "Em breve as primeiras
    ferramentas chegam por aqui."

**Teste existente que muda de comportamento**: `FrameworksTest::test_empty_state_shown_with_no_frameworks_published`
usa `User::factory()->create()` (tier `start` por padrão, não-admin, não-mentor) com zero
frameworks — hoje espera ver "Nenhum framework publicado ainda.", mas com esta mudança essa pessoa
passa a ver o cadeado em vez disso. Esse teste precisa ser reescrito pra reflect o novo
comportamento (testar o cadeado pra um usuário comum, e a mensagem antiga só pra quem tem bypass).
Todos os outros testes de estado vazio já existentes em Aulas (`test_category_filter_with_no_matching_lessons_shows_an_empty_state_message`,
`test_empty_state_shows_the_search_term_when_nothing_matches`) criam pelo menos uma aula antes de
filtrar — continuam intocados, porque `totalCount > 0` nesses cenários.

## 5. Substituição do dropdown em `lesson-player.blade.php`

O dropdown Alpine (`x-data="{ open: false }"`) + pílula desabilitada quando `$lesson->materials->isEmpty()`
vira um link simples, sempre clicável, apontando pra `membros.aulas.materiais`:

```blade
<div class="mt-4">
    <a href="{{ route('membros.aulas.materiais', $lesson) }}" wire:navigate
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-card border border-sand text-ink hover:bg-paper">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 fill-current">
            <path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Z"/>
        </svg>
        Materiais de aula
    </a>
</div>
```

Não é mais condicional a `$lesson->materials->isNotEmpty()` — a página de destino decide o que
mostrar (lista real, "Nenhum material para esta aula ainda.", ou o cadeado se o sistema inteiro
estiver vazio). Usado em duas telas (`aulas.blade.php` e `dashboard.blade.php`, ambas via
`<x-lesson-player>`), então a mudança se aplica às duas de uma vez.

## 6. Testes

- `Tests\Unit\LessonMaterialTest`: adiciona os 6 casos de `icon_label` (mesma tabela do
  `VaultDocumentTest`, adaptada pro nome do accessor): PDF, VÍDEO, XLSX, DOC, LINK (link externo sem
  extensão reconhecível), ARQUIVO (extensão desconhecida).
- `Tests\Feature\Livewire\Membros\AulaMateriaisTest` (novo arquivo): 404 pra aula inexistente; 404
  pra aula CLUB acessada por membro `start`; lista os materiais reais da aula quando existem; mostra
  "Nenhum material para esta aula ainda." quando a aula não tem nenhum mas o sistema tem materiais
  em outra aula (não mostra o cadeado); mostra o cadeado quando `LessonMaterial::count() === 0` no
  sistema inteiro pra usuário comum; mostra a lista real (vazia, com a mensagem de sempre) pro
  mentor/admin mesmo com o sistema inteiro vazio; link de download aponta pra
  `membros.materials.download` quando é upload; link externo abre em nova aba quando é `file_url`.
- `Tests\Feature\Livewire\Membros\AulasTest`: novo teste — cadeado aparece pra usuário comum com
  `totalCount === 0`; novo teste — mentor/admin vê o conteúdo real (mensagem de vazio de sempre) com
  `totalCount === 0`.
- `Tests\Feature\Livewire\Membros\FrameworksTest`: reescreve `test_empty_state_shown_with_no_frameworks_published`
  pra testar o cadeado num usuário comum; novo teste — mentor/admin vê "Nenhum framework publicado
  ainda." mesmo com zero frameworks.
- Um teste cobrindo o link "Materiais de aula" em `lesson-player.blade.php` apontando pro
  `route('membros.aulas.materiais', $lesson)` (pode entrar em `AulasTest` ou
  `DashboardTest`, o que fizer mais sentido na hora do plano).

## 7. Fora de escopo

- Cadeado em Cofre/Pessoas/Dossiês — vazio ali é pessoal/relacional, não "catálogo não publicado"
  (decisão explícita do usuário).
- Biblioteca de materiais única pro catálogo inteiro — já rejeitada explicitamente na #14; esta
  página é por-aula.
- Qualquer alteração no Filament (`LessonMaterialsRelationManager`) — o CRUD de materiais continua
  exatamente como está, só a exibição pro membro muda.
