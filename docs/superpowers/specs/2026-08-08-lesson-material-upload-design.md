# Upload de materiais de aula (.pdf/.doc/.docx) com download forçado

(Pedido direto do usuário, fora do backlog de issues — extensão do trabalho de materiais de aula
concluído na issue #17.)

## Contexto

Hoje `LessonMaterial` (`app/Models/LessonMaterial.php`) só suporta um link externo
(`file_url`, string obrigatória). No painel admin, `MaterialsRelationManager`
(`app/Filament/Resources/Lessons/RelationManagers/MaterialsRelationManager.php`) expõe só esse
campo. Na área de membros, `resources/views/livewire/membros/dashboard.blade.php:38-43` renderiza
cada material como `<a href="{{ $material->file_url }}" target="_blank">`.

O pedido: permitir que o admin faça upload direto do arquivo (`.pdf`, `.doc`, `.docx`) em vez de
(ou além de) colar um link externo, e que o membro consiga baixar esse arquivo diretamente
(download forçado, não só abrir em nova aba).

## Escopo

- Migration + model: novo campo `file_path` (nullable) em `lesson_materials`; `file_url` passa a
  ser nullable também.
- Admin: `MaterialsRelationManager` ganha um campo de upload (`FileUpload`) ao lado do campo de
  URL existente. Um material precisa ter **pelo menos um** dos dois preenchidos (não são
  mutuamente exclusivos, mas nenhum é sozinho obrigatório).
- Membro: rota dedicada de download que força `Content-Disposition: attachment` para materiais
  com arquivo enviado; materiais só com link externo continuam abrindo em nova aba como hoje.

## Decisões

- **Validação "pelo menos um":** usa o `->requiredWithout()` nativo do Filament em ambos os
  campos (`file_url->requiredWithout('file_path')` e `file_path->requiredWithout('file_url')`),
  sem precisar de lógica de toggle/estado condicional no form.
- **Tipos de arquivo aceitos:** `->acceptedFileTypes()` restrito a
  `application/pdf`, `application/msword` (`.doc`),
  `application/vnd.openxmlformats-officedocument.wordprocessingml.document` (`.docx`).
- **Disco/diretório de armazenamento:** disco `public`, diretório `lesson-materials` — mesmo
  disco já usado para thumbnails de aula (`app/Filament/Resources/Lessons/Schemas/LessonForm.php`,
  já configurado desde a issue #17, `storage:link` já rodado neste projeto).
- **Download forçado via rota dedicada**, não link direto pro storage público: um `<a href>`
  apontando direto pra `/storage/lesson-materials/xyz.pdf` deixaria o navegador decidir (PDF abre
  inline na maioria dos navegadores). Rota nova `GET /membros/materiais/{material}/download`
  (nome `membros.materials.download`), protegida por `auth`+`verified` (mesmo middleware da rota
  `/membros`), com um controller invocável
  `app/Http/Controllers/Membros/LessonMaterialDownloadController.php` (segue o padrão já usado
  pelo `VerifyEmailController` do Breeze nesse projeto) chamando
  `Storage::disk('public')->download($material->file_path, "{$material->title}.{$extensão}")` —
  o nome do arquivo baixado usa o `title` do material, não o nome aleatório gerado pelo upload do
  Filament, pra ficar legível pro membro. Se o material não tem `file_path` (só link externo), a
  rota retorna 404.
- **Materiais só com link externo continuam como hoje:** `<a href="{{ $material->file_url }}"
  target="_blank" rel="noopener">` sem mudança — forçar download em URLs de terceiros (Drive, S3
  de outra conta, etc.) não é confiável entre origens diferentes, então não se tenta.
- **Sem alteração no controle de acesso:** qualquer membro autenticado e verificado já vê todas
  as aulas hoje (sem paywall por curso), então a rota de download não precisa de autorização além
  de `auth`+`verified` — mesma superfície de acesso que a página `/membros` já tem.

## Mudanças

### Banco de dados
- Nova migration: adiciona `file_path` (string, nullable) à tabela `lesson_materials`; altera
  `file_url` para nullable.

### `app/Models/LessonMaterial.php`
- `file_path` adicionado a `$fillable`.
- Novo método `hasUploadedFile(): bool` (`filled($this->file_path)`).

### `app/Filament/Resources/Lessons/RelationManagers/MaterialsRelationManager.php`
- Form: `file_url` (`TextInput->url()->requiredWithout('file_path')`), novo campo `file_path`
  (`FileUpload`, disco `public`, diretório `lesson-materials`, tipos aceitos
  pdf/doc/docx, `->requiredWithout('file_url')`).
- Tabela: a coluna `file_url` (hoje mostra a URL crua) é substituída por uma coluna `Tipo`
  (`TextColumn` com `->badge()`, texto "Upload" ou "Link" a partir de
  `hasUploadedFile()`) — evita uma coluna em branco para materiais só-upload e uma coluna com
  path de storage ilegível para materiais só-link. `title` continua a coluna principal.

### `routes/web.php`
- Nova rota `GET membros/materiais/{material}/download`, middleware `auth`+`verified`, nome
  `membros.materials.download`.

### `app/Http/Controllers/Membros/LessonMaterialDownloadController.php` (novo)
- Invocável, recebe `LessonMaterial $material` via route model binding, `abort_unless(
  $material->hasUploadedFile(), 404)`, retorna `Storage::disk('public')->download(...)`.

### `resources/views/livewire/membros/dashboard.blade.php`
- O loop de materiais (linhas 38-43) passa a checar `$material->hasUploadedFile()`: se true, o
  `href` aponta pra `route('membros.materials.download', $material)` sem `target="_blank"`
  (download, não navegação em nova aba); se false, mantém o comportamento atual
  (`$material->file_url`, `target="_blank"`).

## Testes

- Unit: `LessonMaterial::hasUploadedFile()` com `file_path` presente/ausente.
- Feature (admin): `MaterialsRelationManager` — criar material só com upload, criar só com URL,
  validação falha quando nenhum dos dois é preenchido (regressão do `requiredWithout`).
- Feature (membro): usuário autenticado+verificado baixando um material com `file_path` recebe
  uma resposta de download (`Content-Disposition: attachment`) com o conteúdo correto; acessar a
  rota de download de um material sem `file_path` retorna 404; visitante não autenticado é
  redirecionado pro login (mesmo padrão de `/membros`).

Suíte completa (`php artisan test`) deve continuar verde.
