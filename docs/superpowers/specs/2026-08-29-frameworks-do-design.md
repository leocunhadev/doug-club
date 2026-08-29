# Frameworks DO (fecha o item #2 do lista-spec) — página real /membros/frameworks

Recria, no app real, a página `/prototype/frameworks` (referência: `resources/views/prototype/frameworks.blade.php`
+ `config/prototype.php`'s `frameworks` key), como um tipo de conteúdo real e administrável: cada
framework tem um PDF pra baixar e, opcionalmente, uma aula vinculada de verdade (não por título como
o protótipo, por `lesson_id`). Fecha o item #2 do `lista-spec.md`, cuja parte "Biblioteca de aulas"
já foi entregue.

## 1. Contexto

Confirmado com o usuário:

- **Sem gate de tier.** Verificado em `PrototypeController::frameworks()`: os 4 frameworks aparecem
  pra qualquer persona (start/club/mentor), sem filtro nenhum. Diferente da Biblioteca de aulas
  (onde `tier` no `Lesson` decide o que cada um vê), Frameworks é conteúdo universal — só exige estar
  logado. Nenhum campo de tier entra no model `Framework`.
- **"Ver aula" abre a aula específica.** O protótipo é só um link genérico pra `/prototype/aulas`
  (o campo `aula` do config nem chega a ser usado no template). No app real, como a relação
  `lesson_id` é de verdade, o link leva direto pra Biblioteca de aulas já com aquela aula em
  destaque no player — melhoria real sobre o protótipo, custo baixo.

## 2. Modelo de dados

Migration `create_frameworks_table`:

```
frameworks
  id
  code           string           -- "4S", "DOR", "DPD", "CAFÉ" — texto curto contornado no card
  title          string           -- "Consumidor 4S"
  description    text
  pdf_url        string nullable  -- link externo (mesmo padrão de lesson_materials)
  pdf_path       string nullable  -- upload local (disk 'public'), mesmo padrão de lesson_materials
  lesson_id      FK -> lessons, nullable, nullOnDelete
  position       integer default 0  -- ordena o grid (desc = mais recente primeiro)
  timestamps
```

`Framework` (model): `$fillable` = todos os campos acima exceto `id`/timestamps.
`hasUploadedFile(): bool` — `filled($this->pdf_path)`, mesmo padrão de `LessonMaterial::hasUploadedFile()`.
`lesson(): BelongsTo` — `Lesson::class`.

Se um `Lesson` vinculado for apagado, `lesson_id` vira `null` (`nullOnDelete`) em vez de apagar o
framework junto — a ferramenta continua existindo mesmo sem aula associada no momento.

## 3. Rotas

```php
Route::get('membros/frameworks', Frameworks::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.frameworks');

Route::get('membros/frameworks/{framework}/download', FrameworkPdfDownloadController::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.frameworks.download');
```

Nenhuma das duas leva `tier:*` — confirma a decisão da seção 1. `FrameworkPdfDownloadController`
espelha `LessonMaterialDownloadController` (streaming do disco `public`, 404 se não tiver arquivo ou
o arquivo sumiu do disco), só que sem a checagem de `isAvailableFor()` que o de aula tem, porque não
existe tier aqui.

## 4. Página `/membros/frameworks`

### `App\Livewire\Membros\Frameworks`

```php
class Frameworks extends Component
{
    use ComputesUserInitials;

    #[Computed]
    public function frameworks()
    {
        return Framework::query()->with('lesson')->orderByDesc('position')->get();
    }

    public function render()
    {
        return view('livewire.membros.frameworks');
    }
}
```

Sem estado além do computed — não há filtro nem ação de "assistir" nesta página (isso vive na
Biblioteca de aulas).

### View (`resources/views/livewire/membros/frameworks.blade.php`)

1. `x-membros.header`
2. Cabeçalho: "Frameworks DO" (mesmo tratamento Syne/800/tracking do H1 da Início/Aulas) + subtítulo
   "As ferramentas proprietárias do método Decisão Orientada. Cada uma tem o material para baixar e
   a aula que ensina a aplicar."
3. Grid (`<x-framework-card>` por `Framework`), com estado vazio ("Nenhum framework publicado ainda.")
   seguindo o mesmo padrão `@forelse` que a Aulas já usa — sem isso, a página nasce vazia e sem
   explicação até o primeiro framework ser cadastrado.

### `<x-framework-card :framework="...">`

Estrutura/CSS portados de `.fw`/`.fw .num`/`.fw h3`/`.fw p`/`.fw .foot` do protótipo (seção 4 do CSS
original), mesmo padrão já usado pra `.aula-card-*` (classes próprias em `app.css`, cores via
`theme('colors.brand')`, shell do card em Tailwind — `bg-card border-sand rounded shadow` — não
CSS custom):

- `code` grande contornado (`-webkit-text-stroke`), topo do card.
- `title` (h3).
- `description` (p, `flex:1` pra empurrar o rodapé pra baixo em cards de altura desigual no grid).
- Rodapé com dois botões:
  - "Baixar PDF" — três estados, mesmo padrão que `<x-lesson-player>` já usa pros materiais de aula
    (upload local vs. link externo vs. nada):
    - `hasUploadedFile()` → `<a href="{{ route('membros.frameworks.download', $framework) }}">` (via
      o controller da seção 3, que faz streaming do disco).
    - senão, `pdf_url` preenchido → `<a href="{{ $framework->pdf_url }}" target="_blank" rel="noopener">`
      (link direto, sem passar pelo controller — mesmo tratamento que `LessonMaterial::file_url` recebe).
    - senão → span desabilitado "PDF em breve".
  - "Ver aula" — só renderiza se `$framework->lesson_id` existir e a aula ainda existir (`with('lesson')`
    resolve isso): `<a href="{{ route('membros.aulas', ['lesson' => $framework->lesson_id]) }}">`.

## 5. Deep-link "Ver aula" → Aulas

`App\Livewire\Membros\Aulas` ganha um `mount()` próprio (hoje usa só o da trait
`TracksLessonProgress`), que primeiro roda o comportamento padrão da trait e depois, se a query
string tiver `?lesson={id}` **e** essa aula existir **e** `isAvailableFor(auth()->user())` for
verdadeiro pra ela, substitui `featuredLessonId` por ela:

```php
use TracksLessonProgress {
    mount as protected traitMount;
}

public function mount(DetermineFeaturedLesson $determineFeaturedLesson): void
{
    $this->traitMount($determineFeaturedLesson);

    $requestedLessonId = request()->integer('lesson');

    if ($requestedLessonId) {
        $lesson = Lesson::find($requestedLessonId);

        if ($lesson && $lesson->isAvailableFor(Auth::user())) {
            $this->featuredLessonId = $lesson->id;
        }
    }
}
```

Se o parâmetro apontar pra uma aula inexistente ou trancada pro tier do usuário, cai silenciosamente
no comportamento padrão da trait (última assistida / mais recente disponível) — nunca um erro, nunca
um vazamento. Nenhuma outra parte da Aulas muda: o grid, os filtros de categoria e o resto do player
continuam exatamente como estão.

## 6. Navegação

`PersonaNavigation::tabs()`: o item `Frameworks` (`route: 'membros.frameworks'`) vira
`available: true` nas listas `start` e `club` — mesma mecânica usada pra destravar `Aulas`. Isso
automaticamente destrava a aba no header (nada mais lê essa config nesta spec — o card "Atalhos" da
Início já tem "Frameworks DO" apontando pra essa rota desde a spec da Início, só esperando o
`available` virar `true`).

## 7. Admin (Filament)

`App\Filament\Resources\Frameworks\FrameworkResource`, espelhando 1:1 a estrutura de
`App\Filament\Resources\Lessons\LessonResource` (mesmos 3 arquivos de Pages, um Schema, uma Table):

- **Form**: `TextInput` code/title, `Textarea` description, `FileUpload` pdf_path (disk `public`,
  diretório `framework-pdfs`), `Select` lesson_id (`relationship('lesson', 'title')`, nullable/searchable),
  `TextInput` position (numeric, default 0).
- **Table**: colunas code/title/lesson.title/position, `defaultSort('position', 'desc')`,
  `EditAction`/`DeleteAction`, sem filtro dedicado (poucos registros esperados).

Sem isso, a página nasce sem forma de cadastrar frameworks — mesma lição da Biblioteca de aulas.

## 8. Testes

- `Tests\Unit\FrameworkTest`: `hasUploadedFile()` true/false, `lesson()` resolve o relacionamento.
- `Tests\Feature\Livewire\Membros\FrameworksTest`: guest redireciona pro login; grid mostra todos os
  frameworks pra qualquer tier (start e club, sem diferença); estado vazio aparece sem frameworks
  cadastrados; botão de PDF mostra os 3 estados corretos (upload → link de download; só `pdf_url` →
  link externo direto; nenhum dos dois → "PDF em breve"); "Ver aula" só aparece quando há `lesson_id`
  com aula existente.
- `Tests\Feature\Membros\FrameworkPdfDownloadTest`: espelha `LessonMaterialDownloadTest` (guest
  redireciona; download funciona pra qualquer tier autenticado — não há gate; 404 sem arquivo
  uploadado; 404 se o arquivo sumiu do disco).
- `Tests\Feature\Livewire\Membros\AulasTest`: novo teste pro `?lesson=` — aula válida e disponível
  vira featured; aula inexistente ou trancada pro tier do viewer é ignorada, cai no comportamento
  padrão (sem erro, sem vazamento).
- `Tests\Feature\Admin\FrameworkResourceTest`: espelha `LessonResourceTest` (non-admin 403; criar;
  editar; deletar; upload de PDF resolve URL pública).
- `Tests\Unit\Support\PersonaNavigationTest` / `Tests\Feature\Membros\PersonaNavigationTest`: mesma
  atualização de `available:false→true` já feita pra Aulas, agora pra Frameworks.

## 9. Fora de escopo

- Qualquer versão da relação "framework → múltiplas aulas" — é 1 aula opcional por framework, igual
  o protótipo mostra (`aula` é uma string única no config, não uma lista).
- Busca/filtro na grid de frameworks — só 4 registros no protótipo, grid simples sem filtro por
  enquanto (like a Biblioteca de aulas fez com categoria, mas frameworks não tem categoria pra
  filtrar).
