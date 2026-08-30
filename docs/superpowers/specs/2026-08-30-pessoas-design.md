# Pessoas do CLUB (issue #23) — rede entre membros / pontes

Fecha a issue #23. Recria `/prototype/pessoas` (referência: `resources/views/prototype/pessoas.blade.php`)
como uma listagem real de membros CLUB com perfil de rede (empresa, bio, tags "pode ensinar"/"quer
aprender") e um pedido de "ponte" que o mentor vê.

## 1. Contexto

Confirmado com o usuário:

- **O próprio membro preenche o perfil de rede** (empresa, bio, tags) — não é curado pelo mentor nem
  mockado. Editado numa seção nova na página de conta já existente (`/profile`), ao lado de
  nome/e-mail/foto.
- **Tags como texto livre separado por vírgula** — sem vocabulário fixo. Guardadas como array (JSON)
  no banco, reexibidas como string separada por vírgula na hora de editar.
- **Sem matching de verdade nesta versão** — a página só lista todos os membros CLUB (exceto o
  próprio usuário), sem busca, filtro ou sugestão automática. O nome "Pessoas" no nav já existe
  (`App\Support\PersonaNavigation`, hoje `available: false`); só libera.
- **"Pedir a ponte" persiste e aparece pro mentor** — cria um registro (`BridgeRequest`), sem
  e-mail/notificação automática. O mentor vê a lista via Filament (mesmo padrão de
  `MentorSessionResource`: só listagem, `DeleteAction` disponível — apagar o registro é como o
  mentor "arquiva" um pedido já atendido; não há campo de status separado).
- **Perfis incompletos aparecem mesmo assim**, com placeholder no lugar do que falta — evita uma
  página vazia no lançamento, quando a maioria ainda não preencheu nada.

## 2. Modelo de dados

### 2.1 Campos de perfil de rede em `users`

Migration `add_network_profile_fields_to_users_table`:

```
users (novas colunas, todas nullable)
  company      string
  bio          text
  teach_tags   json   -- array de strings, "pode ensinar"
  learn_tags   json   -- array de strings, "quer aprender"
```

`User` model: adiciona `company`, `bio`, `teach_tags`, `learn_tags` ao `#[Fillable(...)]` existente;
`$casts` ganha `'teach_tags' => 'array'`, `'learn_tags' => 'array'`.

### 2.2 `bridge_requests`

Migration `create_bridge_requests_table`:

```
bridge_requests
  id
  requester_id   FK -> users, cascadeOnDelete   -- quem pediu a ponte
  target_id      FK -> users, cascadeOnDelete   -- com quem
  timestamps
```

`BridgeRequest` (model): `$fillable = ['requester_id', 'target_id']`. `requester(): BelongsTo`,
`target(): BelongsTo` (`User::class`, FK explícita nos dois — nem `requester_id` nem `target_id`
batem com a inferência padrão do Eloquent).

## 3. Edição do perfil de rede (`/profile`)

Nova seção `<section>` no componente Volt existente
(`resources/views/livewire/profile/update-profile-information-form.blade.php`), abaixo da seção de
foto/nome/e-mail já existente, com submit próprio (não compartilha o botão "Save" da seção de
nome/e-mail — são ações independentes). Só aparece pra `auth()->user()->tier === 'club'`
(`@if (auth()->user()->tier === 'club')` em volta da seção) — Start e mentor não aparecem na
listagem de Pessoas (mentor é filtrado explicitamente, Start não tem acesso à rota), então não faz
sentido oferecer esses campos a eles.

Novas propriedades públicas: `public string $company = ''`, `public string $bio = ''`,
`public string $teachTagsInput = ''`, `public string $learnTagsInput = ''`.

`mount()` (adicionado ao já existente) popula a partir do usuário:

```php
$this->company = Auth::user()->company ?? '';
$this->bio = Auth::user()->bio ?? '';
$this->teachTagsInput = implode(', ', Auth::user()->teach_tags ?? []);
$this->learnTagsInput = implode(', ', Auth::user()->learn_tags ?? []);
```

Novo método:

```php
public function updateNetworkProfile(): void
{
    $validated = $this->validate([
        'company' => ['nullable', 'string', 'max:255'],
        'bio' => ['nullable', 'string', 'max:500'],
        'teachTagsInput' => ['nullable', 'string', 'max:255'],
        'learnTagsInput' => ['nullable', 'string', 'max:255'],
    ]);

    $user = Auth::user();
    $user->company = $validated['company'] ?: null;
    $user->bio = $validated['bio'] ?: null;
    $user->teach_tags = $this->parseTags($validated['teachTagsInput']);
    $user->learn_tags = $this->parseTags($validated['learnTagsInput']);
    $user->save();

    $this->dispatch('profile-updated', name: $user->name);
}

private function parseTags(?string $input): array
{
    return collect(explode(',', $input ?? ''))
        ->map(fn (string $tag) => trim($tag))
        ->filter()
        ->values()
        ->all();
}
```

View: `x-text-input` para empresa. Não existe um componente `<x-textarea>` no projeto (só há
`Textarea` do Filament, que é admin-only) — bio usa um `<textarea>` cru com as mesmas classes que
`x-text-input` aplica (`border-sand text-ink focus:border-brand focus:ring-brand rounded-md
shadow-sm`), `wire:model="bio"`, `rows="3"`. Dois `x-text-input` (um por tags, com placeholder
`"Vendas, Copywriting, Gestão"`) para `teachTagsInput`/`learnTagsInput`, rótulos "Pode ensinar" /
"Quer aprender". Botão "Salvar" + `<x-action-message on="profile-updated">Salvo.</x-action-message>`,
mesmo padrão da seção existente.

## 4. Página `/membros/pessoas`

```php
Route::get('membros/pessoas', Pessoas::class)
    ->middleware(['auth', 'verified', 'active', 'tier:club'])
    ->name('membros.pessoas');
```

### `App\Livewire\Membros\Pessoas`

```php
class Pessoas extends Component
{
    use ComputesUserInitials;

    #[Computed]
    public function members()
    {
        return User::query()
            ->where('tier', 'club')
            ->where('id', '!=', Auth::id())
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function requestedTargetIds(): array
    {
        return BridgeRequest::query()
            ->where('requester_id', Auth::id())
            ->pluck('target_id')
            ->all();
    }

    public function requestBridge(int $targetId): void
    {
        if ($targetId === Auth::id()) {
            return;
        }

        $isClubMember = User::query()
            ->where('id', $targetId)
            ->where('tier', 'club')
            ->exists();

        if (! $isClubMember) {
            return;
        }

        if (in_array($targetId, $this->requestedTargetIds, true)) {
            return;
        }

        BridgeRequest::create([
            'requester_id' => Auth::id(),
            'target_id' => $targetId,
        ]);
    }

    public function render()
    {
        return view('livewire.membros.pessoas');
    }
}
```

`requestBridge` valida de novo (não confia só no que a view já filtrou) porque o `targetId` chega
pelo payload do Livewire, que o cliente controla: sem essas checagens, um membro poderia mandar
qualquer id (o próprio, um `start`, um inexistente) direto na requisição. `requestedTargetIds` é
recalculado do zero a cada request — o cache do `#[Computed]` não atravessa requests (mesmo padrão
já usado em `Cofre::documents()`), então a UI reflete o novo pedido no próximo render sem precisar
invalidar nada manualmente.

### View

1. `x-membros.header`
2. Cabeçalho: "Gente do CLUB" + subtítulo (copiado do protótipo): "Cada pessoa aqui foi escolhida.
   Veja o que cada uma ensina e quer aprender, e peça a ponte. O Douglas apresenta com contexto."
3. Grade `.people` de cards `.person`, um por `$this->members`:
   - `.top`: avatar inline (não existe componente `<x-membros.avatar>` — o header também resolve
     isso inline, com classes Tailwind próprias; aqui usa a classe `.avatar` portada, ver seção 6):
     `@if ($member->photo_url) <img src="{{ $member->photo_url }}" class="avatar" ...> @else <div
     class="avatar">{{ $member->initials }}</div> @endif` — mesma condição foto-ou-iniciais do
     header, sem a variante `.avatar.o` (laranja) nesta versão, já que não há critério pra decidir
     quem recebe a cor alternativa (o protótipo usa `in_array($ini, ['MP','AR'])`, um mock sem
     significado real). Depois nome (`b`) + empresa (`small`, **omitida inteiramente** se `company`
     vazio — não mostra placeholder aqui, só some a linha).
   - `.bio`: texto da bio, ou `"Ainda não contou nada sobre si."` se vazia.
   - Bloco "Pode ensinar" (`.lbl` + tags `.tag.ensina`): se `teach_tags` vazio, mostra
     `"Ainda não preencheu."` no lugar das tags.
   - Bloco "Quer aprender" (`.lbl` + tags `.tag`): mesmo tratamento com `learn_tags`.
   - `.foot`: botão. Se `$member->id` já está em `$this->requestedTargetIds`: `<button disabled>`
     texto "Pedido enviado", estilo apagado (`disabled:opacity-40 disabled:cursor-not-allowed`,
     mesmo padrão de `nps-modal.blade.php`). Senão: `<button wire:click="requestBridge({{ $member->id }})">`
     texto "Pedir a ponte", estilo `rounded-full bg-black text-white` (mesmo padrão de
     `framework-card.blade.php`/`encontro-card.blade.php` — **não** as classes `.btn`/`.btn.solid`
     do protótipo, que não foram portadas pro app real; todo botão real já usa utilitário Tailwind
     direto).

Sem estado vazio dedicado ("nenhum membro CLUB ainda") documentado nesta versão — se a query
retornar vazio, a grade `.people` simplesmente não renderiza nenhum card; não é um cenário
plausível em produção (sempre há pelo menos os membros CLUB de teste/reais já cadastrados), mas o
teste de feature cobre o caso trivial mesmo assim.

## 5. Admin (Filament) — `BridgeRequestResource`

Espelha a estrutura de `MentorSessionResource` (só listagem, sem create/edit):

```php
class BridgeRequestResource extends Resource
{
    protected static ?string $model = BridgeRequest::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;
    protected static ?string $recordTitleAttribute = 'id';

    public static function table(Table $table): Table
    {
        return BridgeRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListBridgeRequests::route('/')];
    }
}
```

Table (`BridgeRequestsTable`): colunas `requester.name` ("Quem pediu"), `target.name` ("Com quem"),
`created_at` ("Quando", `dateTime('d/m/Y H:i')`, sortable). `defaultSort` por `created_at` desc.
`DeleteAction` no `recordActions` (mentor apaga quando já resolveu o pedido — mesmo padrão de
`MentorSessionsTable`, sem campo de status dedicado).

## 6. CSS

Porta as classes do protótipo relevantes (`.people`, `.person` e seus descendentes, `.avatar`,
`.tag`, `.tag.ensina`, `.lbl`) pro padrão já estabelecido no projeto — **não** porta `.avatar.o`
(variante laranja), já que a seção 4 não usa essa variante (sem critério real pra decidir quem a
recebe; o protótipo usa uma lista mockada arbitrária):

```css
.people { display:grid; grid-template-columns:repeat(auto-fill,minmax(290px,1fr)); gap:16px; }
.person { padding:22px; display:flex; flex-direction:column; gap:12px; }
.person .top { display:flex; gap:14px; align-items:center; }
.person .top b { font-family:'Syne',sans-serif; font-size:16px; display:block; }
.person .top small { color: theme('colors.stone'); font-size:13px; }
.person .bio { font-size:13.5px; color:#4b4740; }
.person .lbl { font-size:11px; font-weight:700; letter-spacing:.1em; text-transform:uppercase;
  color: theme('colors.stone'); margin-bottom:5px; }
.person .foot { display:flex; gap:8px; margin-top:auto; padding-top:6px; }
.avatar { width:44px; height:44px; border-radius:50%; flex-shrink:0; display:flex;
  align-items:center; justify-content:center; font-family:'Syne',sans-serif; font-weight:800;
  font-size:15px; color:#fff; background: theme('colors.black'); object-fit:cover; }
.tag { display:inline-block; font-size:12px; font-weight:500; padding:4px 10px; border-radius:99px;
  background: theme('colors.paper'); border:1px solid theme('colors.sand'); margin:0 4px 6px 0; }
.tag.ensina { background: theme('colors.brand-soft'); border-color:#FFD2BC; color:#B23800; }
```

`object-fit:cover` só se aplica quando `.avatar` está num `<img>` (caso com foto); é ignorado no
`<div>` de iniciais. `.avatar` não colide com nada existente (grep confirma: nenhuma outra parte do
app usa essa classe — os avatares de header/plan-switcher usam Tailwind inline, não uma classe
própria).
O card `.person` usa a mesma sombra/borda do `.card` genérico do protótipo — como o app já tem um
padrão equivalente (`rounded-[18px] border border-sand bg-card shadow-...`, usado em Cofre/Aulas),
o markup do card aplica essas classes Tailwind diretamente no wrapper, e só as classes acima (sem
equivalente Tailwind direto — grid de auto-fill, tipografia Syne, avatar circular) vão pro CSS.

## 7. Navegação

`PersonaNavigation::tabs()`: `Pessoas` (`route: 'membros.pessoas'`, na lista `club`) vira
`available: true`.

## 8. Testes

- `Tests\Unit\BridgeRequestTest` (mesma convenção de `Tests\Unit\VaultDocumentTest` — direto em
  `tests/Unit/`, sem subpasta `Models`): `requester()`/`target()` resolvem via FK explícita; registro
  apagado quando `requester` é apagado (`cascadeOnDelete`); registro apagado quando `target` é
  apagado (`cascadeOnDelete`, teste separado do anterior).
- `Tests\Feature\Membros\PessoasTest` (Livewire): guest redireciona; não-club (`start`) 403/redirect
  via `tier:club`; lista mostra outros membros CLUB mas não o próprio usuário logado; membro com
  perfil vazio aparece com os placeholders de bio/tags; `requestBridge` cria um `BridgeRequest` e a
  UI passa a mostrar "Pedido enviado"; chamar `requestBridge` de novo pro mesmo alvo não duplica o
  registro; `requestBridge` com o próprio id não cria nada; `requestBridge` com um id de usuário
  `start` (não-club) não cria nada.
- `Tests\Feature\ProfileTest` (já existe, testa o mesmo componente Volt via
  `Volt::test('profile.update-profile-information-form')` — os casos novos entram nesse arquivo, não
  num arquivo separado): `updateNetworkProfile` salva company/bio/tags; tags separadas por vírgula
  com espaços extras (`"Vendas,  Copywriting ,Gestão"`) viram `['Vendas', 'Copywriting', 'Gestão']`;
  campo vazio vira array vazio; `mount()` popula os campos públicos a partir de um usuário já com
  `company`/`bio`/tags preenchidos (mesmo padrão dos testes `test_header_shows_*` já existentes no
  arquivo, que verificam estado inicial do componente renderizado); a seção "Perfil na rede CLUB"
  aparece em `/profile` pra um usuário `tier=club` e não aparece pra `tier=start`.
- `Tests\Feature\Admin\BridgeRequestResourceTest`: non-admin 403; lista mostra os registros
  existentes com nome de quem pediu e de quem é o alvo; `DeleteAction` remove o registro.
- `Tests\Unit\Support\PersonaNavigationTest` / `Tests\Feature\Membros\PersonaNavigationTest`: mesma
  atualização de `available:false→true`, agora pra `Pessoas`.

## 9. Fora de escopo

- Matching automático (sugestão/ordenação por tags que batem) — issue futura se o usuário quiser.
- Busca/filtro por tag na listagem — idem.
- Notificação por e-mail quando alguém pede uma ponte — o mentor só vê via Filament.
- Status de acompanhamento do pedido de ponte (pendente/atendido) além de existir ou não — apagar o
  registro no Filament é o único mecanismo de "resolver".
- Vocabulário fixo de tags — texto livre por enquanto.
- Mentor aparecer na listagem de Pessoas (é filtrada estritamente por `tier = 'club'`, mesmo padrão
  já usado no filtro de `member_id` do Filament de `VaultDocument`).
