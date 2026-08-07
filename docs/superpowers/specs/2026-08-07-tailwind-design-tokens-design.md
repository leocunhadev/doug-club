# Design tokens no Tailwind (cores) — Issue #10

## Contexto

A área de membros (`resources/views/layouts/membros.blade.php`, `components/lesson-card*.blade.php`,
`components/membros/header.blade.php`, `livewire/membros/dashboard.blade.php`) usa duas cores de fundo
com valores hex arbitrários repetidos via sintaxe `bg-[#...]` do Tailwind, em vez de tokens nomeados:

- `#0a0a0b` (fundo da página) — 1 ocorrência
- `#12141a` (fundo de cards/painéis) — 6 ocorrências em 5 arquivos

Isso é referenciado na spec do produto (`docs/lms-spec.md`, seção 7 "Design tokens") e listado como
pendente na seção 8 (status de implementação).

## Escopo

Formalizar as duas cores hex arbitrárias como tokens no `tailwind.config.js` (Tailwind v3 clássico via
PostCSS) e substituir todas as ocorrências hardcoded pelas classes geradas.

**Fora de escopo:** aliasar cores que já são nomes padrão do Tailwind (`orange-500`, `red-600`,
`gray-400`, `slate-800`) — elas já funcionam como tokens ao serem referenciadas pelo nome; um alias
por cima delas (`accent`, `muted`) adicionaria indireção sem reduzir duplicação real. Tipografia e
espaçamento da seção 7 (`space-y-16/20`, `mb-2`, `tracking-wide`) já usam a escala padrão do Tailwind —
não há valor arbitrário ali para tokenizar.

## Mudanças

### `tailwind.config.js`

Adicionar em `theme.extend.colors`:

```js
colors: {
    canvas: '#0a0a0b',
    surface: '#12141a',
},
```

### Views (substituição 1:1)

| Antes | Depois |
|---|---|
| `bg-[#0a0a0b]` | `bg-canvas` |
| `bg-[#12141a]` | `bg-surface` |

Arquivos afetados:
- `resources/views/layouts/membros.blade.php`
- `resources/views/components/lesson-card.blade.php`
- `resources/views/components/lesson-card-simple.blade.php`
- `resources/views/components/membros/header.blade.php`
- `resources/views/livewire/membros/dashboard.blade.php`

## Testes

Nenhum teste automatizado cobre classes CSS diretamente. Validação:
- `npm run build` (ou `vite build`) compila sem erro.
- Inspeção visual: a área de membros renderiza com as mesmas cores de antes (mudança é puramente de
  nomenclatura, sem alteração de valor de cor).
- Suíte de testes existente (Pest/PHPUnit) continua verde — não deve haver asserção sobre classes CSS
  específicas que quebre.
