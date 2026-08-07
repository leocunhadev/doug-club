# Tailwind Color Design Tokens Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the two hardcoded hex colors (`#0a0a0b`, `#12141a`) used throughout the membros area with named Tailwind tokens (`canvas`, `surface`), per issue #10 and `docs/lms-spec.md` section 7.

**Architecture:** Pure refactor, no behavior change. Add two colors to `tailwind.config.js` under `theme.extend.colors`, then do a 1:1 find-and-replace of the arbitrary-value classes (`bg-[#0a0a0b]`, `bg-[#12141a]`) with the generated utility classes (`bg-canvas`, `bg-surface`) across the 5 Blade files that use them. Rendered output is byte-identical; only the CSS class names in the markup change.

**Tech Stack:** Tailwind CSS v3 (classic config, via PostCSS — not the v4 `@theme` CSS syntax), Laravel Blade views.

## Global Constraints

- Do not change any color *value* — `#0a0a0b` and `#12141a` must resolve to the exact same hex after the rename. This is a naming refactor only.
- Do not touch `slate-800/60`, `orange-500`, `red-600`, or `gray-400` usages — per the approved spec (`docs/superpowers/specs/2026-08-07-tailwind-design-tokens-design.md`), these already function as tokens via their standard Tailwind names and are out of scope.
- Full existing test suite (`php artisan test`) must stay green — this change must not alter any HTML structure, only class attribute values.

---

### Task 1: Add color tokens and update all usages

**Files:**
- Modify: `tailwind.config.js`
- Modify: `resources/views/layouts/membros.blade.php`
- Modify: `resources/views/components/lesson-card.blade.php`
- Modify: `resources/views/components/lesson-card-simple.blade.php`
- Modify: `resources/views/components/membros/header.blade.php`
- Modify: `resources/views/livewire/membros/dashboard.blade.php`

**Interfaces:**
- Produces: Tailwind utility classes `bg-canvas` (`#0a0a0b`) and `bg-surface` (`#12141a`), available to any Blade view in the project going forward.

- [ ] **Step 1: Add the two tokens to `tailwind.config.js`**

Current file:

```js
import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
```

Change the `theme.extend` block to:

```js
    theme: {
        extend: {
            colors: {
                canvas: '#0a0a0b',
                surface: '#12141a',
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },
```

- [ ] **Step 2: Replace `bg-[#0a0a0b]` with `bg-canvas` in `resources/views/layouts/membros.blade.php`**

Find:
```html
<body class="font-sans antialiased bg-[#0a0a0b]">
```

Replace with:
```html
<body class="font-sans antialiased bg-canvas">
```

- [ ] **Step 3: Replace `bg-[#12141a]` with `bg-surface` in `resources/views/components/lesson-card.blade.php`**

Find (line 6):
```html
{{ $attributes->class(['group relative shrink-0 w-64 text-left rounded-xl overflow-hidden bg-[#12141a] border border-slate-800/60 transition hover:scale-[1.02] hover:brightness-110']) }}
```

Replace with:
```html
{{ $attributes->class(['group relative shrink-0 w-64 text-left rounded-xl overflow-hidden bg-surface border border-slate-800/60 transition hover:scale-[1.02] hover:brightness-110']) }}
```

- [ ] **Step 4: Replace `bg-[#12141a]` with `bg-surface` in `resources/views/components/lesson-card-simple.blade.php`**

Find (line 6):
```html
{{ $attributes->class(['group relative shrink-0 w-64 text-left rounded-xl overflow-hidden bg-[#12141a] border border-slate-800/60 transition hover:scale-[1.02] hover:brightness-110']) }}
```

Replace with:
```html
{{ $attributes->class(['group relative shrink-0 w-64 text-left rounded-xl overflow-hidden bg-surface border border-slate-800/60 transition hover:scale-[1.02] hover:brightness-110']) }}
```

- [ ] **Step 5: Replace `bg-[#12141a]` with `bg-surface` in `resources/views/components/membros/header.blade.php`**

Find (line 13):
```html
<x-dropdown align="right" width="48" contentClasses="py-1 bg-[#12141a] border border-slate-800/60">
```

Replace with:
```html
<x-dropdown align="right" width="48" contentClasses="py-1 bg-surface border border-slate-800/60">
```

- [ ] **Step 6: Replace all three `bg-[#12141a]` occurrences with `bg-surface` in `resources/views/livewire/membros/dashboard.blade.php`**

Find (line 13):
```html
<div class="mt-6 rounded-2xl border border-slate-800/60 bg-[#12141a] p-3 sm:p-4">
```
Replace with:
```html
<div class="mt-6 rounded-2xl border border-slate-800/60 bg-surface p-3 sm:p-4">
```

Find (line 29):
```html
class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-[#12141a] border border-slate-800/60 text-gray-200 hover:bg-slate-800/60">
```
Replace with:
```html
class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-surface border border-slate-800/60 text-gray-200 hover:bg-slate-800/60">
```

Find (line 37):
```html
class="absolute left-0 z-10 mt-2 min-w-[14rem] rounded-lg border border-slate-800/60 bg-[#12141a] py-1 shadow-lg">
```
Replace with:
```html
class="absolute left-0 z-10 mt-2 min-w-[14rem] rounded-lg border border-slate-800/60 bg-surface py-1 shadow-lg">
```

Find (line 47):
```html
<span class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-[#12141a] border border-slate-800/60 text-gray-500 cursor-not-allowed">
```
Replace with:
```html
<span class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-surface border border-slate-800/60 text-gray-500 cursor-not-allowed">
```

- [ ] **Step 7: Confirm no hardcoded occurrences remain**

Run: `grep -rn "bg-\[#0a0a0b\]\|bg-\[#12141a\]" resources/views`
Expected: no output (empty result).

- [ ] **Step 8: Build assets to confirm the config compiles and generates the new classes**

Run: `npm run build`
Expected: build succeeds with no Tailwind/PostCSS errors.

- [ ] **Step 9: Run the full test suite to confirm no regression**

Run: `php artisan test`
Expected: all tests pass (same pass count as before this change — this refactor touches no PHP logic or Blade structure, only class name strings).

- [ ] **Step 10: Commit**

```bash
git add tailwind.config.js resources/views/layouts/membros.blade.php resources/views/components/lesson-card.blade.php resources/views/components/lesson-card-simple.blade.php resources/views/components/membros/header.blade.php resources/views/livewire/membros/dashboard.blade.php
git commit -m "Add canvas/surface Tailwind color tokens, closing #10"
```
