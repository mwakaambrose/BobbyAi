# Warm UI Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reskin the app from the stock Laravel React starter kit into a warm sand + terracotta, Tailwind-UI-application-template-style product called Bobby.

**Architecture:** The whole redesign hangs off one leverage point — `resources/css/app.css` defines shadcn's design tokens, and every component consumes them by name. Rewriting the token *values* (keeping the *names*) recolours the entire app in one task. Typography, identity, shared chrome, and page layout follow as independent layers on top. No routes, models, or agent logic change; the single backend change is one additive controller (Task 6), which is covered by a real TDD cycle.

**Tech Stack:** Laravel 13, Inertia v3, React 19, Tailwind CSS v4, shadcn/ui (Radix), lucide-react, Bunny Fonts via `laravel-vite-plugin/fonts`, Pest 5.

**Spec:** `docs/superpowers/specs/2026-08-03-warm-ui-redesign-design.md`

## Global Constraints

- **No new dependencies.** Everything is achievable with what is installed. Do not run `npm install` or `composer require`.
- **Token names are frozen.** Change the *values* of CSS custom properties, never their names. Renaming a token breaks every shadcn component silently.
- **No hardcoded colours.** No `text-white`, `bg-zinc-900`, `stroke-neutral-900`, or hex literals in components. Everything routes through a token. Existing violations in touched files must be removed.
- **Backend is out of scope** except Task 6, which is additive only.
- **Product name comes from `config('app.name')`**, never a hardcoded `"Bobby"` string in a component.
- **Every task ends green.** The gate is: `npx tsc --noEmit`, `npm run lint:check`, `npm run build`, `php artisan test --compact`, and `vendor/bin/pint --dirty --format agent` if any PHP changed.
- **Colour space is OKLCH.** Match the existing file's format exactly: `oklch(L C H)` with alpha as `oklch(L C H / N%)`.
- **Prettier formatting.** The repo uses 4-space indent and single quotes in `.tsx` (except `components/ui/*`, which is upstream shadcn style with 2-space and double quotes — preserve each file's existing style). Run `npm run format` before committing if unsure.

## Testing Approach — read this before Task 1

This is a presentation change. Ten of the twelve tasks add **no new automated tests**, and that is deliberate, not an oversight:

- The existing 36 feature tests are Inertia render assertions (`->component('dashboard')->has('pendingInvitations', 1)`). They already fail if a page component is renamed, a prop is dropped, or a page throws during render. They are the regression net.
- `npx tsc --noEmit` is the real correctness check for React changes — a renamed prop or wrong type fails the build.
- Visual correctness cannot be asserted in Pest. It is verified by screenshotting each page in **both** light and dark via Playwright.

**Task 6 is the exception** and gets a full red-green-refactor cycle, because it changes controller behaviour.

Do not invent snapshot tests or DOM-assertion tests to satisfy a TDD reflex. They would assert Tailwind class strings, which is testing the implementation rather than the behaviour, and they would need rewriting on every visual tweak.

## File Structure

**Foundation**
| File | Responsibility |
|---|---|
| `resources/css/app.css` | Modify — all colour, radius, shadow, and font tokens |
| `resources/views/app.blade.php` | Modify:23-31 — pre-hydration background must match `--background` |
| `vite.config.ts` | Modify:14-18 — register Fraunces, Inter, JetBrains Mono via Bunny |

**Identity**
| File | Responsibility |
|---|---|
| `resources/js/components/app-logo-icon.tsx` | Modify — Bobby monogram replaces the Laravel bird |
| `resources/js/components/app-logo.tsx` | Modify — drop hardcoded `text-white dark:text-black` |
| `resources/js/components/app-sidebar.tsx` | Modify:47-58 — delete starter-kit footer links |
| `public/favicon.svg` | Replace — Bobby mark |
| `.env`, `.env.example` | Modify — `APP_NAME=Bobby` |

**Shared chrome**
| File | Responsibility |
|---|---|
| `resources/js/components/heading.tsx` | Modify — sole owner of display (serif) type |
| `resources/js/components/page-header.tsx` | **Create** — title + description + actions slot, used by every page |
| `resources/js/components/ui/button.tsx` | Modify — radius, warm shadow, terracotta primary |
| `resources/js/components/ui/card.tsx` | Modify — hairline border + warm shadow |
| `resources/js/components/ui/badge.tsx` | Modify — add `gate`, `verified`, `live` variants |
| `resources/js/components/ui/input.tsx` | Modify — warm field and focus ring |
| `resources/js/components/ui/sidebar.tsx` | Modify — active pill, hairline separators |
| `resources/js/components/app-sidebar-header.tsx` | Modify — breadcrumbs + actions slot + border |

**Pages** — `studio/index`, `dashboard`, `posts/show`, `runs/index`, `runs/show`, `accounts/index`, `brand/edit`, `teams/index`, `teams/edit`, `settings/*`, `auth/*` (7), `welcome`.

**Backend (Task 6 only)** — `app/Http/Controllers/DashboardController.php`, `tests/Feature/DashboardTest.php`.

---

## Task 1: Design tokens

The single highest-leverage change. Every shadcn component reads these names, so this task alone recolours the entire application.

**Files:**
- Modify: `resources/css/app.css:10-159`
- Modify: `resources/views/app.blade.php:23-31`

**Interfaces:**
- Consumes: nothing.
- Produces: the token vocabulary every later task uses — `bg-background`, `bg-card`, `bg-sidebar`, `text-foreground`, `text-muted-foreground`, `border-border`, `bg-primary`, `text-primary`, `bg-agent-muted`, `text-agent`, `text-gate`, `text-verified`, plus `shadow-xs|sm|md|lg` (now warm-tinted) and `rounded-lg` (now `0.75rem`).

- [ ] **Step 1: Replace the `:root` block**

Replace lines 72-110 of `resources/css/app.css` entirely:

```css
:root {
    --background: oklch(0.972 0.006 84);
    --foreground: oklch(0.22 0.012 55);
    --card: oklch(0.995 0.003 84);
    --card-foreground: oklch(0.22 0.012 55);
    --popover: oklch(0.995 0.003 84);
    --popover-foreground: oklch(0.22 0.012 55);
    --primary: oklch(0.55 0.13 38);
    --primary-foreground: oklch(0.985 0.006 80);
    --secondary: oklch(0.945 0.008 80);
    --secondary-foreground: oklch(0.22 0.012 55);
    --muted: oklch(0.945 0.008 80);
    --muted-foreground: oklch(0.53 0.014 60);
    --accent: oklch(0.945 0.008 80);
    --accent-foreground: oklch(0.22 0.012 55);
    --destructive: oklch(0.52 0.17 27);
    --destructive-foreground: oklch(0.52 0.17 27);
    --border: oklch(0.905 0.008 78);
    --input: oklch(0.905 0.008 78);
    --ring: oklch(0.55 0.13 38 / 45%);
    --chart-1: oklch(0.55 0.13 38);
    --chart-2: oklch(0.62 0.09 55);
    --chart-3: oklch(0.66 0.13 68);
    --chart-4: oklch(0.56 0.09 152);
    --chart-5: oklch(0.62 0.03 70);
    --radius: 0.75rem;
    --agent: oklch(0.55 0.13 38);
    --agent-muted: oklch(0.55 0.13 38 / 12%);
    --gate: oklch(0.66 0.13 68);
    --verified: oklch(0.56 0.09 152);
    --sidebar: oklch(0.958 0.008 82);
    --sidebar-foreground: oklch(0.22 0.012 55);
    --sidebar-primary: oklch(0.55 0.13 38);
    --sidebar-primary-foreground: oklch(0.985 0.006 80);
    --sidebar-accent: oklch(0.925 0.012 76);
    --sidebar-accent-foreground: oklch(0.22 0.012 55);
    --sidebar-border: oklch(0.9 0.009 78);
    --sidebar-ring: oklch(0.55 0.13 38 / 45%);
}
```

Note `--sidebar` is *deeper* than `--background`. The layout uses `Sidebar variant="inset"`, so the content card floats above the sidebar ground — this is what makes it read as lifted rather than flat.

- [ ] **Step 2: Replace the `.dark` block**

Replace lines 112-149 entirely. Every neutral is brown-tinted (hue 55); nothing is pure gray:

```css
.dark {
    --background: oklch(0.19 0.008 55);
    --foreground: oklch(0.955 0.006 80);
    --card: oklch(0.225 0.009 55);
    --card-foreground: oklch(0.955 0.006 80);
    --popover: oklch(0.225 0.009 55);
    --popover-foreground: oklch(0.955 0.006 80);
    --primary: oklch(0.68 0.13 40);
    --primary-foreground: oklch(0.2 0.02 45);
    --secondary: oklch(0.27 0.01 55);
    --secondary-foreground: oklch(0.955 0.006 80);
    --muted: oklch(0.27 0.01 55);
    --muted-foreground: oklch(0.7 0.012 70);
    --accent: oklch(0.27 0.01 55);
    --accent-foreground: oklch(0.955 0.006 80);
    --destructive: oklch(0.4 0.14 26);
    --destructive-foreground: oklch(0.7 0.19 26);
    --border: oklch(0.3 0.01 55);
    --input: oklch(0.3 0.01 55);
    --ring: oklch(0.68 0.13 40 / 50%);
    --chart-1: oklch(0.68 0.13 40);
    --chart-2: oklch(0.72 0.09 58);
    --chart-3: oklch(0.78 0.13 72);
    --chart-4: oklch(0.7 0.09 155);
    --chart-5: oklch(0.72 0.03 72);
    --agent: oklch(0.68 0.13 40);
    --agent-muted: oklch(0.68 0.13 40 / 16%);
    --gate: oklch(0.78 0.13 72);
    --verified: oklch(0.7 0.09 155);
    --sidebar: oklch(0.165 0.008 55);
    --sidebar-foreground: oklch(0.955 0.006 80);
    --sidebar-primary: oklch(0.68 0.13 40);
    --sidebar-primary-foreground: oklch(0.2 0.02 45);
    --sidebar-accent: oklch(0.27 0.01 55);
    --sidebar-accent-foreground: oklch(0.955 0.006 80);
    --sidebar-border: oklch(0.28 0.01 55);
    --sidebar-ring: oklch(0.68 0.13 40 / 50%);
}
```

- [ ] **Step 3: Add the warm shadow scale to `@theme`**

Insert immediately after the `--radius-sm` line (currently line 18) in the `@theme` block:

```css
    --shadow-2xs: 0 1px 0 0 oklch(0.35 0.03 55 / 4%);
    --shadow-xs: 0 1px 2px 0 oklch(0.35 0.03 55 / 6%);
    --shadow-sm:
        0 1px 2px 0 oklch(0.35 0.03 55 / 6%),
        0 1px 3px 0 oklch(0.35 0.03 55 / 8%);
    --shadow-md:
        0 2px 4px -1px oklch(0.35 0.03 55 / 7%),
        0 4px 10px -2px oklch(0.35 0.03 55 / 10%);
    --shadow-lg:
        0 4px 8px -2px oklch(0.35 0.03 55 / 8%),
        0 12px 24px -4px oklch(0.35 0.03 55 / 12%);
```

- [ ] **Step 4: Fix the pre-hydration background**

Replace lines 23-31 of `resources/views/app.blade.php`. The current values are `oklch(1 0 0)` and `oklch(0.145 0 0)` — the old neutral tokens. Leaving them causes a white flash on every page load:

```blade
        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(0.972 0.006 84);
            }

            html.dark {
                background-color: oklch(0.19 0.008 55);
            }
        </style>
```

- [ ] **Step 5: Build and verify no token is orphaned**

Run: `npm run build`
Expected: build succeeds. Then run `grep -n "oklch(0.145\|oklch(1 0 0)" resources/css/app.css resources/views/app.blade.php`
Expected: no output. Any hit means an old neutral value survived the rewrite.

- [ ] **Step 6: Run the test suite**

Run: `php artisan test --compact`
Expected: all tests pass. (CSS cannot break them; this confirms a clean baseline before the visual work begins.)

- [ ] **Step 7: Visual check in both themes**

Start the app (`composer run dev` if not already running), then with Playwright visit `/` and the Studio board, and screenshot in light and dark. Expect warm sand surfaces, terracotta buttons, and warm-charcoal (never neutral-gray) dark mode. Nothing else is redesigned yet — layout will still look like the starter kit, and that is correct at this stage.

- [ ] **Step 8: Commit**

```bash
git add resources/css/app.css resources/views/app.blade.php
git commit -m "feat(ui): warm sand and terracotta design tokens"
```

---

## Task 2: Typography

**Files:**
- Modify: `vite.config.ts:14-18`
- Modify: `resources/css/app.css` (the `--font-sans` line in `@theme`)
- Modify: `resources/js/components/heading.tsx`

**Interfaces:**
- Consumes: Task 1's token block.
- Produces: `font-sans` (Inter), `font-serif` (Fraunces), `font-mono` (JetBrains Mono) utilities, and a `Heading` component with the signature `Heading({ title, description, variant }: { title: string; description?: string; variant?: 'default' | 'small' })` — signature unchanged from today, so no caller needs updating.

- [ ] **Step 1: Register the three families**

Replace the `fonts` array in `vite.config.ts` (lines 14-18). All three families and Fraunces italic are confirmed available on Bunny:

```ts
            fonts: [
                bunny('Fraunces', {
                    weights: [400, 500, 600],
                    styles: ['normal', 'italic'],
                }),
                bunny('Inter', {
                    weights: [400, 500, 600],
                }),
                bunny('JetBrains Mono', {
                    weights: [400, 500],
                }),
            ],
```

`optimizedFallbacks` defaults to `true`, which generates metric-matched fallback faces via fontaine — leave it on, it is what prevents layout shift on load.

- [ ] **Step 2: Point the font tokens at the new families**

In `resources/css/app.css`, replace the `--font-sans` declaration (currently lines 11-14) with all three:

```css
    --font-sans:
        'Inter', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji',
        'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';

    --font-serif: 'Fraunces', ui-serif, Georgia, Cambria, serif;

    --font-mono:
        'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
```

- [ ] **Step 3: Make `Heading` the owner of display type**

Replace `resources/js/components/heading.tsx` entirely:

```tsx
export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}) {
    return (
        <header className={variant === 'small' ? '' : 'mb-8 space-y-1'}>
            <h2
                className={
                    variant === 'small'
                        ? 'mb-0.5 font-serif text-base font-medium tracking-[-0.01em]'
                        : 'font-serif text-2xl font-medium tracking-[-0.01em]'
                }
            >
                {title}
            </h2>
            {description && (
                <p className="text-sm text-muted-foreground">{description}</p>
            )}
        </header>
    );
}
```

- [ ] **Step 4: Verify the fonts actually load**

Run: `npm run build`
Expected: build succeeds. Then check the generated font manifest:

```bash
grep -o "fraunces[^\"]*italic[^\"]*" public/build/*fonts*.json | head -3
```

Expected: at least one italic Fraunces entry. Empty output means `styles: ['normal', 'italic']` did not take effect — re-check Step 1 before continuing, since the italic empty states in Task 5 depend on it.

- [ ] **Step 5: Typecheck and lint**

Run: `npx tsc --noEmit && npm run lint:check`
Expected: both clean.

- [ ] **Step 6: Visual check**

Screenshot any settings page (which uses `Heading`). Expect a Fraunces title over Inter body copy. Confirm the serif is genuinely loading and not silently falling back to Georgia — Fraunces has distinctive flared, slightly wonky serifs; Georgia does not.

- [ ] **Step 7: Commit**

```bash
git add vite.config.ts resources/css/app.css resources/js/components/heading.tsx
git commit -m "feat(ui): Fraunces display, Inter UI and JetBrains Mono type system"
```

---

## Task 3: Bobby identity

**Files:**
- Modify: `resources/js/components/app-logo-icon.tsx`
- Modify: `resources/js/components/app-logo.tsx`
- Modify: `resources/js/components/app-sidebar.tsx:2,47-58,80` (imports, `footerNavItems`, `<NavFooter>` usage)
- Create/replace: `public/favicon.svg`
- Modify: `.env`, `.env.example`

**Interfaces:**
- Consumes: Task 1 tokens.
- Produces: `AppLogoIcon(props: SVGAttributes<SVGElement>)` — a `currentColor`-filled mark that inherits colour from its parent, so callers style it with `text-*` utilities and never with a hardcoded colour.

- [ ] **Step 1: Replace the mark**

Replace `resources/js/components/app-logo-icon.tsx` entirely. A rounded squircle with a lowercase "b" counter cut out, so a single `fill-current` renders correctly on any background:

```tsx
import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 32 32"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden
        >
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M10.5 0h11C27.299 0 32 4.701 32 10.5v11C32 27.299 27.299 32 21.5 32h-11C4.701 32 0 27.299 0 21.5v-11C0 4.701 4.701 0 10.5 0Zm2.9 7.4a1.45 1.45 0 0 0-2.9 0v10.85a6.1 6.1 0 1 0 2.9-5.185V7.4Zm3.2 8.05a3.2 3.2 0 1 1 0 6.4 3.2 3.2 0 0 1 0-6.4Z"
            />
        </svg>
    );
}
```

- [ ] **Step 2: Remove the hardcoded colours from the logo container**

Replace `resources/js/components/app-logo.tsx` entirely. The current version forces `text-white dark:text-black` on the icon, which is exactly the kind of hardcoded colour the constraints forbid:

```tsx
import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                <AppLogoIcon className="size-5 fill-current" />
            </div>
            <div className="ml-1 grid flex-1 text-left">
                <span className="mb-0.5 truncate font-serif text-base leading-tight font-medium tracking-[-0.01em]">
                    {name}
                </span>
            </div>
        </>
    );
}
```

- [ ] **Step 3: Delete the starter-kit footer links**

In `resources/js/components/app-sidebar.tsx`:

1. Delete the entire `footerNavItems` declaration (lines 47-58) — it links to `github.com/laravel/react-starter-kit` and the Laravel starter-kit docs, which have nothing to do with this product.
2. Delete the `<NavFooter items={footerNavItems} className="mt-auto" />` line from `<SidebarFooter>`, leaving only `<NavUser />`.
3. Remove the now-unused imports: `NavFooter`, and `BookOpen` and `FolderGit2` from the lucide import. Leave `History`, `LayoutGrid`, `Palette`, `PenLine`, `Plug`.

Do **not** delete `resources/js/components/nav-footer.tsx` — leaving an unused component is harmless; deleting it is scope creep.

- [ ] **Step 4: Replace the favicon**

Write `public/favicon.svg` with the same mark, using the light-mode terracotta as a literal (an SVG file cannot read CSS tokens):

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
    <rect width="32" height="32" rx="8" fill="#faf8f5"/>
    <path fill-rule="evenodd" clip-rule="evenodd" fill="#a8502c" d="M13.4 7.4a1.45 1.45 0 0 0-2.9 0v10.85a6.1 6.1 0 1 0 2.9-5.185V7.4Zm3.2 8.05a3.2 3.2 0 1 1 0 6.4 3.2 3.2 0 0 1 0-6.4Z"/>
</svg>
```

Leave `public/favicon.ico` and `public/apple-touch-icon.png` alone — replacing binary raster assets needs an image toolchain that is not set up here. Note this in the commit body so it is a known, deliberate gap rather than an oversight.

- [ ] **Step 5: Set the product name**

In both `.env` and `.env.example`, set:

```
APP_NAME=Bobby
```

Then run `php artisan config:clear`.

- [ ] **Step 6: Verify the name propagates**

Run: `php artisan config:show app.name`
Expected: `Bobby`.

- [ ] **Step 7: Static gate**

Run: `npx tsc --noEmit && npm run lint:check && npm run build`
Expected: all clean. `tsc` catches it if a lucide import was removed while still in use.

- [ ] **Step 8: Visual check**

Screenshot the sidebar. Expect the terracotta Bobby squircle, "Bobby" in Fraunces, and a footer containing only the user menu — no Repository or Documentation links.

- [ ] **Step 9: Commit**

```bash
git add resources/js/components/app-logo-icon.tsx resources/js/components/app-logo.tsx resources/js/components/app-sidebar.tsx public/favicon.svg .env.example
git commit -m "feat(ui): Bobby wordmark, monogram and favicon

Removes the Laravel starter-kit repository and documentation links from
the sidebar footer. favicon.ico and apple-touch-icon.png still carry the
old mark; replacing raster assets needs an image toolchain."
```

(`.env` is gitignored — only `.env.example` is committed. That is expected.)

---

## Task 4: Shared chrome

**Files:**
- Modify: `resources/js/components/ui/button.tsx:8-38`
- Modify: `resources/js/components/ui/card.tsx:5-16`
- Modify: `resources/js/components/ui/badge.tsx:7-29`
- Modify: `resources/js/components/ui/input.tsx`
- Create: `resources/js/components/page-header.tsx`
- Modify: `resources/js/components/app-sidebar-header.tsx`
- Modify: `resources/js/layouts/app/app-sidebar-layout.tsx`

**Interfaces:**
- Consumes: Task 1 tokens, Task 2 `font-serif`/`font-mono`.
- Produces:
  - `<Badge variant="gate" | "verified" | "live" | ...>` — the only sanctioned way to render status colour.
  - `PageHeader({ title, description, children }: { title: string; description?: string; children?: ReactNode })` — `children` renders right-aligned as page actions. **Every page task from here on uses this.**
  - `AppSidebarHeader({ breadcrumbs, actions }: { breadcrumbs?: BreadcrumbItemType[]; actions?: ReactNode })`.

- [ ] **Step 1: Add status variants to Badge**

In `resources/js/components/ui/badge.tsx`, add three entries to the `variant` map, after `outline`. Keep the file's existing 2-space/double-quote shadcn style:

```tsx
        gate:
          "border-gate/25 bg-gate/12 text-gate [a&]:hover:bg-gate/20",
        verified:
          "border-verified/25 bg-verified/12 text-verified [a&]:hover:bg-verified/20",
        live:
          "border-agent/30 bg-agent-muted text-agent [a&]:hover:bg-agent/20",
```

Also change `rounded-md` to `rounded-full` in the base string — pill-shaped status chips read as status; square ones read as buttons.

- [ ] **Step 2: Warm up the Button**

In `resources/js/components/ui/button.tsx`:

1. In the base string, change `rounded-md` to `rounded-lg` (now 0.75rem via `--radius`) and change `transition-[color,box-shadow]` to `transition-[color,box-shadow,background-color]` so hovers ease rather than snap.
2. Replace the `variant` map:

```tsx
      variant: {
        default:
          "bg-primary text-primary-foreground shadow-sm hover:bg-primary/92 active:bg-primary/88",
        destructive:
          "bg-destructive text-primary-foreground shadow-sm hover:bg-destructive/92 focus-visible:ring-destructive/30",
        outline:
          "border border-border bg-card shadow-xs hover:bg-accent hover:text-accent-foreground",
        secondary:
          "bg-secondary text-secondary-foreground shadow-xs hover:bg-secondary/70",
        ghost: "hover:bg-accent hover:text-accent-foreground",
        link: "text-primary underline-offset-4 hover:underline",
      },
```

3. In the `size` map, change every `rounded-md` to `rounded-lg`.

Note `destructive` now uses `text-primary-foreground` instead of the hardcoded `text-white`.

- [ ] **Step 3: Give Card real depth**

In `resources/js/components/ui/card.tsx`, replace the `Card` className:

```tsx
        "bg-card text-card-foreground flex flex-col gap-6 rounded-xl border border-border py-6 shadow-sm",
```

And make `CardTitle` carry display type:

```tsx
        "font-serif text-base leading-none font-medium tracking-[-0.01em]",
```

- [ ] **Step 4: Warm the Input**

In `resources/js/components/ui/input.tsx`, change `rounded-md` to `rounded-lg` and, if the field sets `bg-transparent`, change it to `bg-card` so inputs read as wells on the sand ground rather than dissolving into it. Leave the focus-ring classes alone — they already resolve through `--ring`, which Task 1 made terracotta.

- [ ] **Step 5: Create the shared page header**

Create `resources/js/components/page-header.tsx`:

```tsx
import type { ReactNode } from 'react';

type Props = {
    title: string;
    description?: string;
    children?: ReactNode;
};

/**
 * The standard page introduction: a display-type title, a muted line of
 * supporting copy, and an optional right-aligned actions slot.
 */
export default function PageHeader({ title, description, children }: Props) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-4">
            <div className="space-y-1">
                <h1 className="font-serif text-2xl font-medium tracking-[-0.01em]">
                    {title}
                </h1>
                {description && (
                    <p className="max-w-prose text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {children && (
                <div className="flex items-center gap-2">{children}</div>
            )}
        </div>
    );
}
```

- [ ] **Step 6: Give the app header an actions slot**

Replace `resources/js/components/app-sidebar-header.tsx` entirely:

```tsx
import type { ReactNode } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
    actions,
}: {
    breadcrumbs?: BreadcrumbItemType[];
    actions?: ReactNode;
}) {
    return (
        <header className="flex h-14 shrink-0 items-center justify-between gap-2 border-b border-border px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            {actions && (
                <div className="flex items-center gap-2">{actions}</div>
            )}
        </header>
    );
}
```

The border moves from `border-sidebar-border/50` to a full-strength `border-border` — at 50% opacity over warm sand it was effectively invisible.

- [ ] **Step 7: Refresh the sidebar's active state**

In `resources/js/components/ui/sidebar.tsx`, find the `sidebarMenuButtonVariants` `cva` call. In its base string change `rounded-md` to `rounded-lg`. In the `data-[active=true]` classes, replace the `bg-sidebar-accent font-medium` treatment with:

```
data-[active=true]:bg-agent-muted data-[active=true]:text-agent data-[active=true]:font-medium
```

This is what turns the active nav item into a terracotta pill instead of a gray block.

- [ ] **Step 8: Static gate**

Run: `npx tsc --noEmit && npm run lint:check && npm run build`
Expected: all clean.

- [ ] **Step 9: Run the suite**

Run: `php artisan test --compact`
Expected: all pass.

- [ ] **Step 10: Visual check**

Screenshot Studio and a settings page in both themes. Expect: terracotta active nav pill, softer buttons with warm shadow, cards with visible hairline + lift, a real header border.

- [ ] **Step 11: Commit**

```bash
git add resources/js/components/ui resources/js/components/page-header.tsx resources/js/components/app-sidebar-header.tsx
git commit -m "feat(ui): warm shared chrome and status badge variants"
```

---

## Task 5: Studio board

The board is the product's main screen and currently its worst one: three full-height dashed rectangles that read as broken when empty.

**Files:**
- Modify: `resources/js/components/agent/board-column.tsx`
- Modify: `resources/js/components/agent/post-card.tsx`
- Modify: `resources/js/components/agent/empty-state.tsx`
- Modify: `resources/js/pages/studio/index.tsx:76-86` (page header) and `:139-145` (board container)

**Interfaces:**
- Consumes: `PageHeader` (Task 4), `Badge` `live` variant (Task 4).
- Produces: `BoardColumn({ label, status, posts, teamSlug }: { label: string; status: string; posts: BoardPost[]; teamSlug: string })` — prop signature unchanged, so `studio/index.tsx`'s `{...column}` spread keeps working.

- [ ] **Step 1: Rebuild the column**

Replace `resources/js/components/agent/board-column.tsx` entirely. The key structural change: a fixed header plus a bounded, scrolling body — not a dashed box stretched to the viewport.

```tsx
import { PostCard } from '@/components/agent/post-card';
import type { BoardPost } from '@/types/agent';

type Props = {
    label: string;
    status: string;
    posts: BoardPost[];
    teamSlug: string;
};

export function BoardColumn({ label, status, posts, teamSlug }: Props) {
    const live = status === 'publishing' && posts.length > 0;

    return (
        <section className="flex w-72 shrink-0 flex-col gap-3">
            <header className="flex items-center gap-2 px-1">
                <h2 className="font-serif text-sm font-medium tracking-[-0.01em]">
                    {label}
                </h2>
                <span
                    className={`rounded-full px-1.5 py-0.5 font-mono text-[11px] tabular-nums ${
                        live
                            ? 'animate-pulse bg-agent-muted text-agent'
                            : 'bg-muted text-muted-foreground'
                    }`}
                >
                    {posts.length}
                </span>
            </header>

            <div
                className={`flex max-h-[calc(100dvh-14rem)] min-h-32 flex-col gap-2 overflow-y-auto rounded-xl border p-2 transition-colors ${
                    live
                        ? 'border-agent/30 bg-agent-muted'
                        : 'border-border bg-muted/40'
                }`}
            >
                {posts.length === 0 ? (
                    <p className="px-2 py-6 text-center font-serif text-sm text-balance text-muted-foreground italic">
                        Nothing here yet.
                    </p>
                ) : (
                    posts.map((post) => (
                        <PostCard
                            key={post.id}
                            post={post}
                            teamSlug={teamSlug}
                        />
                    ))
                )}
            </div>
        </section>
    );
}
```

Three deliberate changes: the dashed border becomes solid with a tinted fill (dashed reads as a drop-zone placeholder, not a container); the column gets a fixed `w-72` instead of `flex-1 basis-0` so six columns scroll horizontally rather than crushing to unreadable widths; and empty columns say so in Fraunces italic rather than presenting a void.

- [ ] **Step 2: Give post cards hierarchy**

Replace the `PostCard` component body in `resources/js/components/agent/post-card.tsx` (keep the `ProgressRing` helper above it exactly as-is — it already uses `stroke-agent` and `stroke-muted`, which are tokens):

```tsx
export function PostCard({ post, teamSlug }: Props) {
    return (
        <Link
            href={show([teamSlug, post.id])}
            className="group block rounded-lg border border-border bg-card p-3 shadow-xs transition-[color,box-shadow,border-color] hover:border-agent/40 hover:shadow-sm focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none"
        >
            <p className="line-clamp-4 text-sm leading-relaxed text-card-foreground">
                {post.body ?? post.brief}
            </p>

            <div className="mt-3 flex items-center justify-between font-mono text-[11px] text-muted-foreground">
                <span>{post.updatedAt}</span>

                {post.progress && (
                    <span className="flex items-center gap-1.5 text-agent">
                        <ProgressRing {...post.progress} />
                        {post.progress.steps}/{post.progress.budget}
                    </span>
                )}
            </div>
        </Link>
    );
}
```

- [ ] **Step 3: Warm the shared empty state**

Replace the returned JSX in `resources/js/components/agent/empty-state.tsx` (keep the `Props` type unchanged):

```tsx
    return (
        <div className="flex flex-col items-center justify-center gap-4 rounded-xl border border-border bg-muted/40 px-6 py-16 text-center">
            <div className="flex size-10 items-center justify-center rounded-full bg-agent-muted text-agent">
                <Icon className="size-5" aria-hidden />
            </div>
            <div className="space-y-1.5">
                <p className="font-serif text-lg font-medium tracking-[-0.01em]">
                    {title}
                </p>
                <p className="max-w-sm font-serif text-sm text-balance text-muted-foreground italic">
                    {description}
                </p>
            </div>
            {children}
        </div>
    );
```

- [ ] **Step 4: Use `PageHeader` on the Studio page**

In `resources/js/pages/studio/index.tsx`, replace the hand-rolled header block (the `<div className="flex flex-wrap items-start justify-between gap-4">` wrapper and its inner `<div className="space-y-0.5">` title stack, lines 76-86) so the `<Dialog>` becomes a child of `PageHeader`:

```tsx
                <PageHeader
                    title="Studio"
                    description="Every post, and exactly where the agent has taken it."
                >
                    <Dialog open={composing} onOpenChange={setComposing}>
```

Close it with `</PageHeader>` where the old wrapper `</div>` was (immediately after `</Dialog>`). Add the import:

```tsx
import PageHeader from '@/components/page-header';
```

- [ ] **Step 5: Style the brief textarea to match the new inputs**

In the same file, the `<textarea name="brief">` carries a hand-written class string with `rounded-md` and `bg-transparent`. Change those two to `rounded-lg` and `bg-card`, and `border-input` stays as-is (it now resolves warm).

- [ ] **Step 6: Let the board scroll properly**

Replace the board container div (line 139) so the columns get their own scroll context:

```tsx
                    <div className="-mx-4 flex gap-3 overflow-x-auto px-4 pb-2">
```

The negative margin plus matching padding lets columns bleed to the viewport edge while keeping the first column aligned with the page header.

- [ ] **Step 7: Static gate**

Run: `npx tsc --noEmit && npm run lint:check && npm run build`
Expected: all clean.

- [ ] **Step 8: Run the Studio tests**

Run: `php artisan test --compact --filter=Studio`
Expected: pass. These assert the `studio/index` component renders with its columns prop — they catch a broken import or a render-time throw.

- [ ] **Step 9: Visual check**

Screenshot Studio in both themes with at least one post present. Verify: columns no longer stretch to full viewport height, empty columns show italic "Nothing here yet.", the count chip is a pill, cards lift on hover, and six columns scroll horizontally rather than crushing.

- [ ] **Step 10: Commit**

```bash
git add resources/js/components/agent resources/js/pages/studio/index.tsx
git commit -m "feat(ui): rebuild the studio board with bounded scrolling columns"
```

---

## Task 6: Dashboard

**This is the only task with a backend change, and the only one with a real TDD cycle.**

The spec calls for a dashboard showing run activity, posts awaiting approval, and account health. `DashboardController` currently passes only `pendingInvitations`, so the data does not exist client-side. The change is strictly additive: existing props and their tests are untouched.

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `tests/Feature/DashboardTest.php`
- Modify: `resources/js/pages/dashboard.tsx`

**Interfaces:**
- Consumes: `PageHeader` (Task 4), `Badge` variants (Task 4).
- Produces: a new Inertia prop `overview` with this exact shape (the React types in Step 5 must match it field for field):

```
overview: {
    awaitingApproval: int,
    published: int,
    activeRuns: int,
    accounts: { connected: int, total: int },
    teamSlug: string,
}
```

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/DashboardTest.php`:

```php
test('dashboard summarises the current team pipeline', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    Post::factory()->count(2)->for($team)->create(['status' => PostStatus::Approved]);
    Post::factory()->for($team)->create(['status' => PostStatus::Published]);
    SocialAccount::factory()->for($team)->create(['status' => AccountStatus::Connected]);
    SocialAccount::factory()->for($team)->create(['status' => AccountStatus::Expired]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('overview.awaitingApproval', 2)
        ->where('overview.published', 1)
        ->where('overview.activeRuns', 0)
        ->where('overview.accounts.connected', 1)
        ->where('overview.accounts.total', 2)
        ->where('overview.teamSlug', $team->slug),
    );
});

test('dashboard overview ignores other teams posts', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();

    Post::factory()->count(3)->for($otherTeam)->create(['status' => PostStatus::Approved]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('overview.awaitingApproval', 0),
    );
});
```

Add the imports at the top of the file, alongside the existing ones:

```php
use App\Enums\AccountStatus;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\SocialAccount;
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `php artisan test --compact --filter="summarises the current team pipeline"`
Expected: FAIL — `Inertia property [overview] does not exist.`

If it fails for a different reason (a missing factory, say), fix that first. A test failing for the wrong reason proves nothing.

- [ ] **Step 3: Add the overview to the controller**

In `app/Http/Controllers/DashboardController.php`, add the overview to the render call and a private method to build it. Do not touch the existing `$pendingInvitations` query:

```php
        return Inertia::render('dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'overview' => $this->overview($request->user()->currentTeam),
        ]);
    }

    /**
     * Summarise the team's publishing pipeline.
     *
     * @return array{awaitingApproval: int, published: int, activeRuns: int, accounts: array{connected: int, total: int}, teamSlug: string}
     */
    private function overview(Team $team): array
    {
        return [
            'awaitingApproval' => $team->posts()->where('status', PostStatus::Approved)->count(),
            'published' => $team->posts()->where('status', PostStatus::Published)->count(),
            'activeRuns' => AgentRun::query()
                ->whereIn('post_id', $team->posts()->select('id'))
                ->whereIn('status', [RunStatus::Queued, RunStatus::Running])
                ->count(),
            'accounts' => [
                'connected' => $team->socialAccounts()->connected()->count(),
                'total' => $team->socialAccounts()->count(),
            ],
            'teamSlug' => $team->slug,
        ];
    }
```

Add these imports:

```php
use App\Enums\PostStatus;
use App\Enums\RunStatus;
use App\Models\AgentRun;
use App\Models\Team;
```

The `activeRuns` count uses a subquery on `post_id` rather than loading posts — this is a dashboard, and an N+1 here is on the hot path of the app's landing page.

- [ ] **Step 4: Run the tests and confirm green**

Run: `php artisan test --compact --filter=Dashboard`
Expected: PASS — all seven dashboard tests, including the five that existed before.

- [ ] **Step 5: Build the dashboard page**

Replace `resources/js/pages/dashboard.tsx` entirely. This removes the three placeholder-pattern boxes and the `min-h-[100vh]` block:

```tsx
import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Plug, Send, Sparkles } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import PageHeader from '@/components/page-header';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard, studio } from '@/routes';
import { index as accountsIndex } from '@/routes/accounts';
import { index as runsIndex } from '@/routes/runs';
import type { DashboardInvitation } from '@/types';

type Overview = {
    awaitingApproval: number;
    published: number;
    activeRuns: number;
    accounts: { connected: number; total: number };
    teamSlug: string;
};

type Props = {
    pendingInvitations?: DashboardInvitation[];
    overview: Overview;
};

function Stat({
    icon: Icon,
    label,
    value,
    hint,
    href,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
    hint: string;
    href: string;
}) {
    return (
        <Card className="gap-0 py-0 transition-[box-shadow,border-color] hover:border-agent/40 hover:shadow-sm">
            <Link href={href} className="block p-5 focus-visible:outline-none">
                <CardContent className="flex items-start gap-4 p-0">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-agent-muted text-agent">
                        <Icon className="size-4" aria-hidden />
                    </div>
                    <div className="space-y-0.5">
                        <p className="font-mono text-2xl leading-none tabular-nums">
                            {value}
                        </p>
                        <p className="text-sm font-medium">{label}</p>
                        <p className="text-xs text-muted-foreground">{hint}</p>
                    </div>
                </CardContent>
            </Link>
        </Card>
    );
}

export default function Dashboard({
    pendingInvitations = [],
    overview,
}: Props) {
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );

    const { teamSlug } = overview;

    return (
        <>
            <Head title="Dashboard" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Overview"
                    description="Where your posts stand, and what the agent is doing about them."
                >
                    <Button asChild>
                        <Link href={studio(teamSlug)}>
                            <Sparkles /> Open studio
                        </Link>
                    </Button>
                </PageHeader>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Stat
                        icon={CheckCircle2}
                        label="Awaiting publish"
                        value={String(overview.awaitingApproval)}
                        hint="Approved, not yet live"
                        href={studio(teamSlug).url}
                    />
                    <Stat
                        icon={Send}
                        label="Published"
                        value={String(overview.published)}
                        hint="Live on LinkedIn"
                        href={studio(teamSlug).url}
                    />
                    <Stat
                        icon={Sparkles}
                        label="Active runs"
                        value={String(overview.activeRuns)}
                        hint="Agent working right now"
                        href={runsIndex(teamSlug).url}
                    />
                    <Stat
                        icon={Plug}
                        label="Accounts"
                        value={`${overview.accounts.connected}/${overview.accounts.total}`}
                        hint="Connected sessions"
                        href={accountsIndex(teamSlug).url}
                    />
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
    ],
});
```

- [ ] **Step 6: Full gate**

Run: `npx tsc --noEmit && npm run lint:check && npm run build && php artisan test --compact && vendor/bin/pint --dirty --format agent`
Expected: all clean. `tsc` verifies the `Overview` type matches what the controller actually sends.

- [ ] **Step 7: Visual check**

Screenshot the dashboard in both themes. Expect four stat cards with terracotta icon discs and mono numerals — no placeholder patterns anywhere.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/DashboardController.php tests/Feature/DashboardTest.php resources/js/pages/dashboard.tsx
git commit -m "feat(ui): replace dashboard placeholders with a real pipeline overview

Adds an additive overview prop to DashboardController covering posts
awaiting publish, published posts, active runs and account health."
```

---

## Task 7: Post workspace and runs

**Files:**
- Modify: `resources/js/pages/posts/show.tsx`
- Modify: `resources/js/pages/runs/index.tsx`
- Modify: `resources/js/pages/runs/show.tsx`
- Modify: `resources/js/components/agent/run-timeline.tsx`
- Modify: `resources/js/components/agent/step-row.tsx`
- Modify: `resources/js/components/agent/thread-message.tsx`
- Modify: `resources/js/components/agent/draft-card.tsx`
- Modify: `resources/js/components/agent/post-preview.tsx`

**Interfaces:**
- Consumes: `PageHeader`, `Badge` `gate`/`verified`/`live` variants, `font-mono`.
- Produces: nothing new. Props and component names are unchanged throughout.

Read each file before editing. Apply this rule set rather than a blanket find-and-replace:

1. Replace any hand-rolled `<h1>`/title-and-subtitle stack with `<PageHeader title=… description=…>`, moving page-level buttons inside it.
2. Replace any inline status colouring (`text-green-600`, `bg-amber-100`, `text-agent` used as a chip, and similar) with `<Badge variant="verified">`, `<Badge variant="gate">`, or `<Badge variant="live">`.
3. Wrap machine data — run ids, step indices, durations, token counts, timestamps, `step_count`/budget pairs — in `font-mono text-[11px] tabular-nums text-muted-foreground`.
4. Replace `border-sidebar-border/70 dark:border-sidebar-border` with `border-border`. The dark-mode-specific override exists only because the old `/70` opacity was invisible in dark; with the warm tokens a single `border-border` is correct in both.
5. Replace `rounded-md`/`rounded-lg` on card-like containers with `rounded-xl`, and give them `bg-card shadow-xs`.
6. Delete every hardcoded colour: `text-white`, `text-black`, `bg-zinc-*`, `stroke-neutral-*`, hex literals.

- [ ] **Step 1: Apply the rules to the five agent components**

Work through `run-timeline.tsx`, `step-row.tsx`, `thread-message.tsx`, `draft-card.tsx`, `post-preview.tsx` in that order. These are 24-88 lines each. As a worked example, a step row's metadata line becomes:

```tsx
            <span className="font-mono text-[11px] tabular-nums text-muted-foreground">
                {step.index} · {step.action}
            </span>
```

and a status indicator becomes:

```tsx
            <Badge variant={step.failed ? 'gate' : 'verified'}>
                {step.status}
            </Badge>
```

adapting the field names to whatever each component actually receives — read the `types/agent.ts` definitions rather than assuming.

- [ ] **Step 2: Apply the rules to the three pages**

`posts/show.tsx` is 200 lines. If any single section (the draft chooser, the approval gate, the message thread) exceeds roughly 80 lines after editing, extract it into a local component declared above the default export in the same file. Do not create new files for this — these sections are not reused elsewhere, and files that change together should live together.

- [ ] **Step 3: Verify no hardcoded colours survive**

Run:

```bash
grep -rnE "text-white|text-black|bg-zinc-|stroke-neutral-|#[0-9a-fA-F]{6}" resources/js/pages/posts resources/js/pages/runs resources/js/components/agent
```

Expected: no output.

- [ ] **Step 4: Static gate**

Run: `npx tsc --noEmit && npm run lint:check && npm run build`
Expected: all clean.

- [ ] **Step 5: Run the relevant tests**

Run: `php artisan test --compact --filter="PostWorkspace|RunReplay|PostLifecycle"`
Expected: pass.

- [ ] **Step 6: Visual check**

Screenshot a post workspace and a run detail page in both themes. Confirm run ids and step indices are in JetBrains Mono, statuses are pill badges, and the approval gate reads as the most important element on the post page.

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages/posts resources/js/pages/runs resources/js/components/agent
git commit -m "feat(ui): restyle post workspace and run views"
```

---

## Task 8: Accounts and Brand

**Files:**
- Modify: `resources/js/pages/accounts/index.tsx`
- Modify: `resources/js/pages/brand/edit.tsx`

**Interfaces:**
- Consumes: `PageHeader`, `Badge`, `Card`.
- Produces: nothing new.

- [ ] **Step 1: Restyle Accounts**

Apply the Task 7 rule set. Additionally, account connection status maps directly onto the new badge variants — this is the clearest use of them in the app:

```tsx
const accountBadge = {
    connected: 'verified',
    expired: 'gate',
    disconnected: 'secondary',
} as const;
```

```tsx
                        <Badge variant={accountBadge[account.status]}>
                            {account.status}
                        </Badge>
```

The status strings come from `App\Enums\AccountStatus`: `connected`, `expired`, `disconnected`. Render `lastVerifiedAt` in `font-mono text-[11px] text-muted-foreground`.

- [ ] **Step 2: Restyle Brand**

Apply the Task 7 rule set. Wrap each group of related fields in a `<Card>` with a `<CardHeader>` naming the group, so the form reads as sections rather than one long stack of inputs.

- [ ] **Step 3: Static gate**

Run: `npx tsc --noEmit && npm run lint:check && npm run build`
Expected: all clean.

- [ ] **Step 4: Run the relevant tests**

Run: `php artisan test --compact --filter="Accounts|BrandProfile"`
Expected: pass.

- [ ] **Step 5: Visual check**

Screenshot both pages in both themes.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/accounts resources/js/pages/brand
git commit -m "feat(ui): restyle accounts and brand pages"
```

---

## Task 9: Teams and settings

**Files:**
- Modify: `resources/js/pages/teams/index.tsx`
- Modify: `resources/js/pages/teams/edit.tsx`
- Modify: `resources/js/pages/settings/profile.tsx`
- Modify: `resources/js/pages/settings/security.tsx`
- Modify: `resources/js/pages/settings/appearance.tsx`
- Modify: `resources/js/layouts/settings/layout.tsx`

**Interfaces:**
- Consumes: `PageHeader`, `Heading`, `Card`, `Badge`.
- Produces: nothing new.

- [ ] **Step 1: Restyle the settings layout and its three pages**

Apply the Task 7 rule set. The settings pages already use `Heading`, which Task 2 upgraded — they mostly need container and border cleanup rather than header changes. In `layouts/settings/layout.tsx`, give the nav an active state consistent with the sidebar: `bg-agent-muted text-agent` on the current item.

- [ ] **Step 2: Restyle the teams pages**

`teams/edit.tsx` is 395 lines — the largest file in the redesign. Extract each major section (members table, invitations list, danger zone) into a local component declared above the default export in the same file. Keep them in `teams/edit.tsx`; they are not reused. Apply the Task 7 rule set throughout.

Team roles map onto badges: owner → `default`, admin → `secondary`, member → `outline`.

- [ ] **Step 3: Static gate**

Run: `npx tsc --noEmit && npm run lint:check && npm run build`
Expected: all clean.

- [ ] **Step 4: Run the relevant tests**

Run: `php artisan test --compact --filter="Team|Profile|Security"`
Expected: pass.

- [ ] **Step 5: Visual check**

Screenshot teams index, team edit, and all three settings pages in both themes. On the appearance page specifically, toggle light → dark → system and confirm each takes effect.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/teams resources/js/pages/settings resources/js/layouts/settings
git commit -m "feat(ui): restyle teams and settings"
```

---

## Task 10: Authentication

**Files:**
- Modify: `resources/js/layouts/auth-layout.tsx:1`
- Modify: `resources/js/layouts/auth/auth-split-layout.tsx`
- Modify: `resources/js/pages/auth/login.tsx`, `register.tsx`, `forgot-password.tsx`, `reset-password.tsx`, `confirm-password.tsx`, `verify-email.tsx`, `two-factor-challenge.tsx`

**Interfaces:**
- Consumes: Task 1 tokens, Task 2 fonts, Task 3 `AppLogoIcon`.
- Produces: `AuthLayout` keeps its existing `{ title, description, children }` signature, so none of the seven pages need prop changes.

- [ ] **Step 1: Switch the auth layout to the split template**

`auth/auth-split-layout.tsx` already exists but is unused. Change line 1 of `resources/js/layouts/auth-layout.tsx`:

```tsx
import AuthLayoutTemplate from '@/layouts/auth/auth-split-layout';
```

- [ ] **Step 2: Rebuild the split layout**

Replace `resources/js/layouts/auth/auth-split-layout.tsx` entirely. The current version hardcodes `bg-zinc-900` and `text-white` — a cold black panel that would look broken against warm sand:

```tsx
import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div className="relative hidden h-full flex-col justify-between bg-sidebar p-10 lg:flex">
                <Link
                    href={home()}
                    className="relative z-20 flex items-center gap-2.5"
                >
                    <span className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                        <AppLogoIcon className="size-5 fill-current" />
                    </span>
                    <span className="font-serif text-lg font-medium tracking-[-0.01em]">
                        {name}
                    </span>
                </Link>

                <blockquote className="relative z-20 max-w-md space-y-2">
                    <p className="font-serif text-xl leading-relaxed text-balance italic">
                        Nothing reaches LinkedIn until you approve the exact
                        words.
                    </p>
                    <footer className="font-mono text-[11px] text-muted-foreground">
                        The approval gate, always on
                    </footer>
                </blockquote>
            </div>

            <div className="w-full lg:p-8">
                <div className="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <Link
                        href={home()}
                        className="relative z-20 flex items-center justify-center lg:hidden"
                    >
                        <span className="flex aspect-square size-10 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                            <AppLogoIcon className="size-6 fill-current" />
                        </span>
                    </Link>
                    <div className="flex flex-col items-start gap-2 text-left sm:items-center sm:text-center">
                        <h1 className="font-serif text-2xl font-medium tracking-[-0.01em]">
                            {title}
                        </h1>
                        <p className="text-sm text-balance text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
```

Note the `absolute inset-0 bg-zinc-900` overlay div is gone entirely — the panel now uses `bg-sidebar`, so it is warm in light mode and warm-charcoal in dark.

- [ ] **Step 3: Sweep the seven auth pages**

Read each and apply the Task 7 rule set. These are mostly `<Form>` + `<Input>` + `<Button>`, so they inherit almost everything from Task 4 — expect small edits: removing leftover `rounded-md` on hand-rolled elements, and replacing any hardcoded colour. `two-factor-challenge.tsx` (133 lines) has the most custom markup; its OTP input should sit in a `bg-card` well with `font-mono`.

- [ ] **Step 4: Static gate**

Run: `npx tsc --noEmit && npm run lint:check && npm run build`
Expected: all clean.

- [ ] **Step 5: Run the auth tests**

Run: `php artisan test --compact --filter=Auth`
Expected: pass. These assert the auth pages render and post correctly — they catch a layout swap that breaks a form.

- [ ] **Step 6: Visual check**

Log out and screenshot all seven auth pages in both themes, plus one at mobile width (390px) to confirm the split panel collapses and the mobile logo appears.

- [ ] **Step 7: Commit**

```bash
git add resources/js/layouts/auth-layout.tsx resources/js/layouts/auth/auth-split-layout.tsx resources/js/pages/auth
git commit -m "feat(ui): warm split-panel authentication screens"
```

---

## Task 11: Welcome page

**Files:**
- Modify: `resources/js/pages/welcome.tsx` (390 lines, full rewrite)

**Interfaces:**
- Consumes: Task 1 tokens, Task 2 fonts, Task 3 `AppLogoIcon`, `Button`.
- Produces: nothing consumed elsewhere.

- [ ] **Step 1: Read the current page and note what must survive**

Before rewriting, record the existing auth-state logic — the page shows "Dashboard" when `auth.user` is set and "Log in"/"Register" otherwise. That behaviour must survive the rewrite. Note the exact route helpers it imports.

- [ ] **Step 2: Rewrite as a marketing page**

Replace `resources/js/pages/welcome.tsx`. Structure it as local components declared above the default export — `Nav`, `Hero`, `HowItWorks`, `Footer` — rather than one 390-line block. The default export composes them.

The hero:

```tsx
function Hero() {
    return (
        <section className="mx-auto max-w-3xl px-6 pt-20 pb-16 text-center">
            <p className="font-mono text-[11px] tracking-widest text-muted-foreground uppercase">
                LinkedIn, on your terms
            </p>
            <h1 className="mt-4 font-serif text-5xl leading-[1.05] font-medium tracking-[-0.02em] text-balance sm:text-6xl">
                An agent that writes your posts, and never publishes one
                without you.
            </h1>
            <p className="mx-auto mt-6 max-w-xl text-lg leading-relaxed text-balance text-muted-foreground">
                Bobby drafts in your voice, shows you exactly what it plans to
                say, and waits. You approve the words. It handles the rest.
            </p>
            <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                <Button size="lg" asChild>
                    <Link href={register()}>Start writing</Link>
                </Button>
                <Button size="lg" variant="outline" asChild>
                    <Link href={login()}>Log in</Link>
                </Button>
            </div>
        </section>
    );
}
```

`HowItWorks` is three columns — Brief, Draft, Approve — each a `<Card>` with a mono step number, a Fraunces heading, and a line of body copy describing what actually happens. Draw the copy from what the product does: a brief goes in, three drafts come back, the approval gate holds until you sign off, then a real browser session publishes.

Keep the auth-aware nav from Step 1: when `auth.user` exists, the nav shows a single "Dashboard" button instead of log in / register.

- [ ] **Step 3: Static gate**

Run: `npx tsc --noEmit && npm run lint:check && npm run build`
Expected: all clean.

- [ ] **Step 4: Verify both auth states**

Screenshot `/` logged out (expect Log in / Register) and logged in (expect Dashboard). Both in light and dark, plus one at 390px width.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/welcome.tsx
git commit -m "feat(ui): rewrite the welcome page as a real marketing page"
```

---

## Task 12: Final sweep and verification

**Files:** any that fail the checks below.

- [ ] **Step 1: Hunt every surviving hardcoded colour**

Run:

```bash
grep -rnE "text-white|text-black|bg-zinc-|bg-neutral-|bg-gray-|bg-slate-|stroke-neutral-|#[0-9a-fA-F]{3,6}\b" resources/js --include=*.tsx
```

Expected: no output outside `resources/js/components/ui/` (upstream shadcn files may legitimately retain a few, e.g. in `sonner.tsx`). Fix anything found in app code.

- [ ] **Step 2: Confirm no orphaned starter-kit references**

Run:

```bash
grep -rn "laravel/react-starter-kit\|Instrument Sans\|PlaceholderPattern" resources/js resources/css vite.config.ts
```

Expected: at most a definition of `PlaceholderPattern` in `components/ui/placeholder-pattern.tsx` with no importers. Any other hit is a leftover.

- [ ] **Step 3: Full verification gate**

Run each and confirm each passes before moving on:

```bash
npx tsc --noEmit
npm run lint:check
npm run build
php artisan test --compact
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: Full-app screenshot pass**

With the app running, visit and screenshot in **both light and dark**: `/`, login, register, forgot-password, reset-password, confirm-password, verify-email, two-factor-challenge, dashboard, studio, a post workspace, runs index, a run detail, accounts, brand, teams index, team edit, settings profile, settings security, settings appearance.

For each pair, check three things specifically:
1. Dark mode is warm-charcoal, not neutral gray — compare a large surface against a true gray to be sure.
2. Text contrast holds. Muted text on tinted surfaces is where a warm palette most often fails WCAG AA.
3. No element still carries a cold-gray border or shadow against the warm ground.

- [ ] **Step 5: Mobile pass**

At 390px width, screenshot the dashboard, studio, login, and welcome. Confirm the sidebar collapses to the sheet, the studio board scrolls horizontally without breaking the page, and no page scrolls horizontally as a whole.

- [ ] **Step 6: Commit any fixes**

```bash
git add -A
git commit -m "fix(ui): final warm-theme sweep across light and dark"
```

---

## Self-Review Notes

**Spec coverage:** Every spec section maps to a task — tokens and pre-hydration background (1), typography (2), identity (3), shared chrome (4), Studio (5), Dashboard (6), post/runs (7), accounts/brand (8), teams/settings (9), auth (10), Welcome (11), verification (12).

**Known deviation from the spec — flag to the user before starting Task 6.** The spec's "Out of Scope" section says no backend changes, but its Dashboard requirement ("a real overview built from existing props") is not achievable: `DashboardController` passes only `pendingInvitations`. Task 6 resolves this with a narrow additive controller change plus two new tests. If the user would rather keep the backend frozen, the alternative is a dashboard of navigation cards with no live numbers — worse, but zero backend risk.

**Known gap:** `public/favicon.ico` and `public/apple-touch-icon.png` keep the Laravel mark (Task 3, Step 4). Replacing raster assets needs an image toolchain that this project does not have.
