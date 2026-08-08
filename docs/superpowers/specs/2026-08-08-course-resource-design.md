# Filament: CourseResource (CRUD de cursos/módulos) — Issue #16

(Sub-issue 2/3 da divisão da issue #11 "Painel admin para cadastrar cursos/aulas/materiais".)

## Contexto

A issue #15 (concluída) instalou o Filament e restringiu `/admin` a usuários com `is_admin = true`
(`app/Providers/Filament/AdminPanelProvider.php`, auto-discovery de resources em
`app/Filament/Resources`). Esta sub-issue adiciona o primeiro Resource: CRUD de `Course`
(`app/Models/Course.php`: `label`, `title`, `description` nullable, `position` integer;
`fillable = ['label', 'title', 'description', 'position']`; relação `lessons()` hasMany ordenada
por `position` desc).

Gestão de aulas e materiais fica para a sub-issue #17.

## Escopo

CRUD completo de `Course` no painel Filament: listar, criar, editar, excluir. Sem gestão de aulas
(a coluna de contagem de aulas é só leitura).

## Decisões

- **Idioma do painel:** inglês padrão do Filament (labels autogerados a partir dos nomes dos
  campos/model, sem tradução) — decisão explícita do usuário, mesmo o resto do produto sendo
  pt-br.
- **Ordenação da tabela:** drag-and-drop via `->reorderable('position', direction: 'desc')` +
  `->defaultSort('position', 'desc')`. O parâmetro `direction: 'desc'` existe justamente para
  este caso — sem ele, arrastar um item para o topo da lista do admin atribuiria o *menor* valor
  de `position`, que na home pública (`Course::lessons()` e a ordenação de seções, ambas
  `orderByDesc('position')`) o jogaria para o *fim* da lista. Com `direction: 'desc'`, o item
  arrastado para o topo do admin recebe o *maior* valor, ficando também em primeiro na home
  pública — comportamento intuitivo para quem edita.
- **Atributo de título do registro:** `label` (não `title`) — usado em breadcrumbs e modais de
  confirmação de exclusão. `title` pode ser string vazia (o curso "Boas Vindas" no seeder tem
  `title => ''`), então `label` é a opção que sempre tem conteúdo legível.
- **Colunas da tabela:** só as que a issue pede — `label`, `title` (ambas buscáveis), `position`,
  contagem de aulas (`lessons_count` via `->counts('lessons')`). As colunas `created_at`/
  `updated_at` que o gerador do Filament adiciona por padrão são removidas, para não extrapolar o
  escopo da issue.
- **Ações de linha:** `EditAction` + `DeleteAction` (exclusão individual), mais bulk delete via
  `DeleteBulkAction` — CRUD completo conforme pedido pela issue.

## Mudanças

### Geração inicial (via Artisan, não hand-rolled)

```bash
php artisan make:filament-resource Course --generate --no-interaction --panel=admin --record-title-attribute=label
```

Gera, com base nas colunas reais de `courses` no banco:
- `app/Filament/Resources/Courses/CourseResource.php`
- `app/Filament/Resources/Courses/Schemas/CourseForm.php`
- `app/Filament/Resources/Courses/Tables/CoursesTable.php`
- `app/Filament/Resources/Courses/Pages/{ListCourses,CreateCourse,EditCourse}.php`

O form gerado (`label`, `title` obrigatórios; `description` textarea; `position` numeric default
0) já bate com o que a issue pede — nenhuma edição necessária ali.

### Customização de `CoursesTable.php`

- Remove as colunas `created_at`/`updated_at`.
- Adiciona coluna `lessons_count` com `->counts('lessons')`.
- Adiciona `->reorderable('position', direction: 'desc')` e `->defaultSort('position', 'desc')`
  ao table builder.
- Adiciona `DeleteAction::make()` junto ao `EditAction::make()` em `recordActions`.

### `CourseResource.php`, `CourseForm.php`, páginas

Sem mudanças em relação ao que o gerador produz (fora o `--record-title-attribute=label` já
aplicado na geração).

## Testes

Feature test novo em `tests/Feature/Admin/CourseResourceTest.php`, cobrindo (como usuário
`is_admin = true`, autenticado):
- Listagem (`/admin/courses`) retorna 200 e exibe um curso existente (ex: pelo `label`).
- Criação: submeter o form com dados válidos cria um `Course` no banco.
- Edição: atualizar um `Course` existente reflete a mudança no banco.
- Exclusão: apagar um `Course` remove o registro do banco.
- Um usuário `is_admin = false` recebe 403 ao acessar `/admin/courses` (reforça o gate da #15
  também neste Resource, já que o Resource é uma superfície nova).

Suíte completa (`php artisan test`) deve continuar verde.
