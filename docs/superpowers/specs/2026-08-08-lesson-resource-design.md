# Filament: LessonResource (CRUD de aulas) + materiais como relation manager — Issue #17

(Sub-issue 3/3, última da divisão da issue #11 "Painel admin para cadastrar cursos/aulas/materiais".)

## Contexto

As issues #15 (Filament + gate admin) e #16 (CourseResource) estão concluídas. Esta é a última
sub-issue: CRUD de `Lesson` (`app/Models/Lesson.php`: `course_id`, `number`, `title`,
`duration_seconds` nullable, `video_provider` enum `vimeo`/`youtube`, `video_id`,
`thumbnail_path` nullable, `published_at` date, `position`) com materiais de aula
(`app/Models/LessonMaterial.php`: `lesson_id`, `title`, `file_url`) geridos como relation
manager dentro do próprio `LessonResource`, sem resource top-level separado — decisão já tomada
no design da #16.

## Escopo

CRUD completo de `Lesson` no painel Filament (`/admin/lessons`), com um relation manager de
`LessonMaterial` na página de edição. Sem upload de vídeo próprio (mantém embed externo
Vimeo/YouTube, conforme `docs/lms-spec.md` seção 2).

## Decisões

- **`course_id` referencia `course.label`, não `course.title`:** mesma razão da #16 — o curso
  "Boas Vindas" tem `title => ''`, então um select/coluna baseado em `title` mostraria uma opção
  em branco. Aplica-se tanto ao campo `Select` do form quanto à coluna `course.label` na tabela e
  ao filtro por curso.
- **`duration_seconds` vira um campo de texto `mm:ss` / `h:mm:ss` no form**, não segundos crus —
  decisão explícita do usuário para melhorar a UX de quem cadastra aulas. Implementado via
  `formatStateUsing` (segundos → string ao carregar o form) e `dehydrateStateUsing` (string →
  segundos ao salvar), com validação por regex
  (`/^(?:\d{1,3}:[0-5]\d|\d{1,2}:[0-5]\d:[0-5]\d)$/`, aceita `5:30` ou `1:15:30`, rejeita
  segundos/minutos ≥ 60 quando há componente de hora).
- **`video_provider` vira um `Select` com opções fixas `vimeo`/`youtube`**, não o `TextInput`
  livre que o gerador do Filament produz (ele não introspecta valores de `enum` do banco) — evita
  que um admin digite um provider inválido que quebraria `Lesson::embedUrl()` (que lança
  `InvalidArgumentException` para providers desconhecidos).
- **`thumbnail_path` vira um `FileUpload` real** (decisão explícita do usuário, ao invés do texto
  simples usado para `video_id`), disco `public`, diretório `lesson-thumbnails`. Requer
  `php artisan storage:link` (ainda não executado neste projeto — `public/storage` não existe).
- **Tabela de aulas**: colunas `course.label`, `number`, `title`, `published_at`, `position` —
  igual ao que a issue pede. As colunas extras que o gerador adiciona por padrão
  (`duration_seconds`, `video_provider`, `video_id`, `thumbnail_path`, `created_at`,
  `updated_at`) são removidas, mesma disciplina de escopo aplicada em `CoursesTable.php` na #16.
- **Filtro por curso**: `SelectFilter::make('course_id')->relationship('course', 'label')`.
- **Reordenação condicionada ao filtro de curso ativo:** `position` é uma ordem *dentro de cada
  curso* (`Course::lessons()` ordena `position` desc por curso), então arrastar aulas sem um
  filtro de curso ativo não tem significado — misturaria aulas de cursos diferentes na mesma
  sequência numérica. Implementado com o terceiro parâmetro `direction` e o segundo parâmetro
  `condition` de `reorderable()`:
  ```php
  ->reorderable(
      'position',
      condition: fn ($livewire): bool => filled($livewire->tableFilters['course_id']['value'] ?? null),
      direction: 'desc',
  )
  ```
  Verificado lendo o código-fonte do Filament: `Table::resolveDefaultClosureDependencyForEvaluationByName()`
  injeta `$livewire` (a página Livewire, que expõe `public ?array $tableFilters`) em closures
  passadas a métodos como `reorderable()`. `direction: 'desc'` segue a mesma lógica da #16 — o
  item arrastado para o topo recebe o maior `position`, batendo com a ordenação pública
  (`orderByDesc('position')`).
- **Relation manager de materiais sem associar/desassociar:** o gerador do Filament
  (`make:filament-relation-manager`) inclui por padrão `AssociateAction`, `DissociateAction` e
  `DissociateBulkAction` para relações `HasMany`. `lesson_materials.lesson_id` é `NOT NULL`
  (`database/migrations/..._create_lesson_materials_table.php`), então "desassociar" um material
  (que tentaria gravar `lesson_id = null`) quebraria a constraint do banco. Essas três ações são
  removidas do relation manager gerado, deixando só `CreateAction`/`EditAction`/`DeleteAction` +
  `DeleteBulkAction` — um material só existe dentro do contexto de uma aula, nunca é
  "reatribuído".
- **Atributo de título do registro:** `title` (ao contrário do Course, o título da aula é sempre
  não-vazio — `NOT NULL` sem default especial no seeder, diferente do caso "Boas Vindas").

## Mudanças

### Geração inicial (via Artisan)

```bash
php artisan make:filament-resource Lesson --generate --no-interaction --panel=admin --record-title-attribute=title
php artisan make:filament-relation-manager Lessons materials title --generate --no-interaction --panel=admin --resource-namespace="App\Filament\Resources\Lessons"
```

Gera:
- `app/Filament/Resources/Lessons/LessonResource.php`
- `app/Filament/Resources/Lessons/Schemas/LessonForm.php`
- `app/Filament/Resources/Lessons/Tables/LessonsTable.php`
- `app/Filament/Resources/Lessons/Pages/{ListLessons,CreateLesson,EditLesson}.php`
- `app/Filament/Resources/Lessons/RelationManagers/MaterialsRelationManager.php`

### Customizações sobre o gerado

- `LessonResource.php`: registrar `MaterialsRelationManager` em `getRelations()` (o gerador avisa
  isso explicitamente na saída do comando, mas não faz automaticamente).
- `LessonForm.php`: `course_id` usa `label` no `->relationship()`; `duration_seconds` ganha
  `formatStateUsing`/`dehydrateStateUsing`; `video_provider` vira `Select`; `thumbnail_path` vira
  `FileUpload`.
- `LessonsTable.php`: reduzir colunas às 5 listadas acima (com `course.label`), adicionar o
  `SelectFilter` de curso, adicionar `->reorderable(...)` condicional e `DeleteAction`.
- `MaterialsRelationManager.php`: remover `AssociateAction`, `DissociateAction`,
  `DissociateBulkAction` das ações da tabela.
- `php artisan storage:link` — necessário para o `FileUpload` do thumbnail funcionar (arquivo
  público acessível).

## Testes

Feature test novo em `tests/Feature/Admin/LessonResourceTest.php`, cobrindo (como usuário
`is_admin = true`):
- Listagem (`/admin/lessons`) retorna 200 e exibe uma aula existente.
- Criação: submeter o form com dados válidos (incluindo `duration_seconds` no formato `mm:ss`)
  cria uma `Lesson`, e o valor persistido no banco é o total em segundos correto.
- Edição: atualizar uma `Lesson` existente reflete a mudança no banco.
- Exclusão: apagar uma `Lesson` remove o registro do banco.
- Reordenação: com o filtro de curso ativo, reordenar aulas dentro do mesmo curso atualiza
  `position` corretamente (mesmo padrão de teste usado na #16 para `CoursesTable`).
- Relation manager de materiais: criar, editar e excluir um `LessonMaterial` a partir da página
  de edição da aula.
- Um usuário `is_admin = false` recebe 403 ao acessar `/admin/lessons`.

Suíte completa (`php artisan test`) deve continuar verde. Ao final desta sub-issue, a issue #11
(tracking) pode ser fechada — as três sub-issues (#15, #16, #17) estarão completas.
