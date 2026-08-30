# Cofre (issue #22) — documentos privados por mentorado

Fecha a issue #22. Recria `/prototype/cofre` (referência: `resources/views/prototype/cofre.blade.php`
+ seed `cofre_docs` em `config/prototype.php`) como um espaço real de documentos privados por
membro CLUB, gerenciado pelo mentor/admin via Filament.

## 1. Contexto

Confirmado com o usuário:

- **Sem marca d'água real no PDF.** O protótipo promete "abrir com marca d'água do seu nome"
  (`onclick="toast('Abrindo ... com marca d'água do seu nome.')"`) — mesma linguagem usada no player
  de vídeo, que já tem marca d'água de verdade (`lesson-watermark.js`). Marcar um PDF de verdade
  exigiria uma lib de manipulação de PDF (nenhuma instalada hoje: `composer show` não retorna
  `fpdi`/`tcpdf`/similar) e gerar uma cópia por download. Fora de escopo nesta versão — só o gate de
  acesso (dono + mentor), igual ao padrão já usado em `LessonMaterial`/`Framework`.
- **Mentor único**, mesma simplificação já usada na Agenda de sessões 1:1 (#20): `mentor_id` fica
  explícito no schema (não hardcoded), mas resolve sempre pro único `User` com `tier = 'mentor'` —
  sem seletor de mentor em lugar nenhum, nem no form do Filament.
- **"Novo" é rastreamento real de leitura**, não um campo fixo definido na hora do upload — um
  `opened_at` nulo no documento é "Novo"; abrir marca como lido. Isso é uma melhoria sobre o
  protótipo (lá `novo` é só um valor fixo no mock, sem lógica de "já vi isso" de verdade).
- **Ícone do documento inferido pela extensão do arquivo**, não um campo manual — evita mais um
  campo pro admin preencher errado/inconsistente.

## 2. Modelo de dados

Migration `create_vault_documents_table`:

```
vault_documents
  id
  member_id     FK -> users, cascadeOnDelete   -- dono do documento
  mentor_id     FK -> users, cascadeOnDelete   -- quem é responsável (resolve pro mentor único)
  title         string
  description   string nullable                -- a legenda, ex: "Construído na Sessão 3 · 22 mai"
  file_url      string nullable                -- link externo (mesmo padrão de LessonMaterial)
  file_path     string nullable                -- upload local (disk 'public')
  opened_at     datetime nullable              -- null = "Novo"; setado no primeiro download
  timestamps
```

`VaultDocument` (model): `$fillable` = todos os campos acima exceto `id`/timestamps.
`member(): BelongsTo`, `mentor(): BelongsTo` (`User::class`, FK explícita nos dois — nem
`member_id` nem `mentor_id` batem com a inferência padrão do Eloquent).
`hasUploadedFile(): bool` — `filled($this->file_path)`, mesmo padrão de `LessonMaterial`.
`isNew(): bool` — `blank($this->opened_at)`.
`iconLabel(): string` (accessor) — deriva um rótulo curto a partir da extensão do arquivo
(`pathinfo(..., PATHINFO_EXTENSION)`, minúsculo) de `file_path` se houver upload, senão de
`file_url`. Mapeamento exato:

| Extensão | Rótulo |
|---|---|
| `pdf` | `PDF` |
| `mp4`, `mov`, `webm` | `VÍDEO` |
| `xlsx`, `xls` | `XLSX` |
| `doc`, `docx` | `DOC` |
| sem `file_path` e `file_url` sem extensão reconhecível (ex.: link do Vimeo sem `.mp4` no path) | `LINK` |
| qualquer outra extensão, ou nenhuma extensão detectável | `ARQUIVO` |

## 3. Rota de download/abertura

```php
Route::get('membros/cofre/{document}/abrir', VaultDocumentOpenController::class)
    ->middleware(['auth', 'verified', 'active', 'tier:club'])
    ->name('membros.cofre.open');
```

`VaultDocumentOpenController`: `abort_unless($document->member_id === auth()->id(), 404)` — só o
dono abre, o mentor não usa essa rota (gerencia pelo Filament). A marcação de `opened_at = now()`
(se ainda nulo) só acontece depois que a checagem de existência do conteúdo passa — nunca antes,
pra não marcar como "lido" um documento que na verdade deu 404:
- Se `hasUploadedFile()`: primeiro `abort_unless(Storage::disk('public')->exists($document->file_path), 404)`
  — só then marca `opened_at`, e então faz o stream de download
  (`Storage::disk('public')->download(...)`), mesmo padrão de `LessonMaterialDownloadController`.
- Senão, se `file_url` setado: marca `opened_at`, depois redireciona (`redirect($document->file_url)`)
  — link externo, não tem arquivo pra servir, então não há checagem de existência prévia.
- Senão: 404, sem marcar `opened_at` (documento sem arquivo nem link — não deveria existir via
  Filament, que exige um dos dois, mas defensivo).

## 4. Página `/membros/cofre`

### `App\Livewire\Membros\Cofre`

```php
class Cofre extends Component
{
    use ComputesUserInitials;

    #[Computed]
    public function documents()
    {
        return VaultDocument::query()
            ->where('member_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();
    }

    public function render()
    {
        return view('livewire.membros.cofre');
    }
}
```

Sem estado além do computed — abrir/marcar-como-lido acontece no controller (seção 3), não aqui,
porque é uma navegação de página (download/redirect), não uma ação Livewire.

### View

1. `x-membros.header`
2. Cabeçalho: "Meu cofre" + subtítulo (copiado do protótipo, ainda verdadeiro: "Tudo que
   construímos juntos, sessão a sessão: insights, planos e materiais que o Douglas preparou para
   você. Só você e ele veem isso.")
3. Aviso (`.cofre-note` portado — ver seção 6): "Documentos com seu nome gravado em cada página.
   Este espaço é individual e intransferível." — texto do protótipo mantido mesmo sem a marca
   d'água real implementada (é uma afirmação sobre rastreabilidade/gate de acesso, que a spec
   entrega de verdade, não sobre o efeito visual de watermark especificamente).
4. Lista (`.doc-row` por `VaultDocument`), com estado vazio ("Nenhum documento no seu cofre ainda.")
   seguindo o mesmo padrão `@forelse` já usado em Aulas/Frameworks/Encontros.

### `<x-vault-document-row :document="...">`

- Ícone (`.doc-ic`): `$document->icon_label`.
- Título (`b`) + descrição (`small`).
- Badge "Novo" (`.novo-pill`) só se `$document->isNew()`.
- Botão "Abrir": `<a href="{{ route('membros.cofre.open', $document) }}">` — sempre, já que a rota
  já resolve upload vs. link externo internamente (diferente de Framework/LessonMaterial, que
  ramificam no template porque o link externo ali é direto sem controller no meio; aqui o link
  externo passa pelo controller porque precisa marcar `opened_at` antes de redirecionar).

## 5. Admin (Filament)

`App\Filament\Resources\VaultDocuments\VaultDocumentResource`, espelhando a estrutura de
`FrameworkResource`:

- **Form**: `Select` member_id (`relationship('member', 'name')`, filtrado a `tier=club` via
  `->relationship('member', 'name', fn ($query) => $query->where('tier', 'club'))`, obrigatório,
  searchable), `TextInput` title (obrigatório), `Textarea` description (opcional), `TextInput`
  file_url (`->url()->requiredWithout('file_path')`), `FileUpload` file_path (disk `public`,
  diretório `vault-documents`, `->requiredWithout('file_url')`). Sem campo `mentor_id` no form —
  setado automaticamente via `mutateFormDataBeforeCreate` pro único `User` com `tier=mentor`.
- **Table**: colunas member.name, title, `hasUploadedFile()`-ou-`file_url` como indicador de tipo
  (texto simples "Upload"/"Link"), `opened_at` (placeholder "Não aberto ainda"). `defaultSort`
  por `created_at` desc. `EditAction`/`DeleteAction`, sem filtro dedicado.

## 6. CSS

Porta as classes do protótipo (seção correspondente de `_styles.blade.php`) pro padrão já
estabelecido no projeto (classes customizadas com `theme()`, shell do card em Tailwind):

```css
.cofre-note { display:flex; gap:12px; align-items:center; padding:14px 18px;
  background: theme('colors.brand-soft'); border:1px solid #FFD2BC; border-radius:14px;
  margin-bottom:18px; font-size:13.5px; color:#7a3a14; }
.doc-row { display:flex; align-items:center; gap:14px; padding:16px 20px;
  border-top:1px solid theme('colors.sand'); }
.doc-row:first-of-type { border-top:none; }
.doc-ic { width:42px; height:46px; border-radius:10px; background: theme('colors.paper');
  border:1px solid theme('colors.sand'); display:flex; align-items:center; justify-content:center;
  font-size:10.5px; font-weight:700; color: theme('colors.brand'); flex-shrink:0; }
.novo-pill { background: theme('colors.brand'); color:#fff; font-size:10px; font-weight:700;
  letter-spacing:.08em; padding:4px 9px; border-radius:99px; text-transform:uppercase; }
```

(`brand-soft` já existe em `tailwind.config.js` — mesmo tom usado no `.eyebrow.laranja`-equivalente
de outras telas.)

## 7. Navegação

`PersonaNavigation::tabs()`: `Meu cofre` (`route: 'membros.cofre'`, na lista `club`) vira
`available: true`.

## 8. Testes

- `Tests\Unit\VaultDocumentTest`: `hasUploadedFile()` true/false; `isNew()` true quando
  `opened_at` nulo, false quando setado; `iconLabel()` cobre os 6 casos da tabela da seção 2
  (`PDF`, `VÍDEO`, `XLSX`, `DOC`, `LINK` pra link externo sem extensão reconhecível, `ARQUIVO`
  pra extensão desconhecida); `member()`/`mentor()` resolvem via FK explícita; documento apagado
  quando o membro é apagado (`cascadeOnDelete`).
- `Tests\Feature\Membros\VaultDocumentOpenTest`: guest redireciona; dono abre (upload → download
  funciona, `opened_at` passa a ser setado); dono abre (link externo → redirect pro `file_url`,
  `opened_at` setado); outro membro CLUB tentando abrir o documento de alguém = 404; 404 se
  upload mas arquivo sumiu do disco; abrir de novo um documento já aberto não falha (idempotente,
  `opened_at` não regride pra mais recente — mantém a primeira data de abertura).
- `Tests\Feature\Livewire\Membros\CofreTest`: guest redireciona; membro só vê os próprios
  documentos (não os de outro membro); badge "Novo" só nos ainda não abertos; estado vazio sem
  documentos cadastrados.
- `Tests\Feature\Admin\VaultDocumentResourceTest`: non-admin 403; lista; cria (com upload e com
  link externo, separadamente); `mentor_id` é setado automaticamente pro mentor único ao criar;
  edita; deleta.
- `Tests\Unit\Support\PersonaNavigationTest` / `Tests\Feature\Membros\PersonaNavigationTest`:
  mesma atualização de `available:false→true`, agora pra `Meu cofre`.

## 9. Fora de escopo

- Marca d'água real no PDF — issue separada se o usuário quiser depois.
- Mentor conseguir abrir/baixar pela mesma rota do membro — ele gerencia via Filament.
- Múltiplos mentores responsáveis por um mesmo documento.
- Notificação por e-mail quando um documento novo é adicionado (o protótipo menciona isso em
  outro contexto — Dossiês — não aqui).
