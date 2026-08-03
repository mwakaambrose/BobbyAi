# Warm UI Redesign — Bobby

**Date:** 2026-08-03
**Status:** Approved design, ready for implementation planning

## Problem

The app runs on the stock Laravel React starter kit skin: neutral-gray shadcn tokens with zero chroma, `Instrument Sans` everywhere, the Laravel bird as the product mark, and sidebar footer links pointing at `laravel/react-starter-kit`. It reads as an unbranded scaffold rather than a product.

Two concrete symptoms visible in the current Studio screenshots:

- The board's three columns render as full-height dashed rectangles. With one card in `Draft`, roughly 80% of the viewport is empty dashed outline, which reads as broken rather than empty.
- Chrome is undifferentiated: the page header is a bare bar with a single collapse toggle, and headings, body copy, and machine data (timestamps, run ids) all sit at the same visual weight.

## Goal

A warm, professional application UI in the style of Tailwind UI's application templates — deliberately not the cold violet-on-white "AI SaaS" default. The redesign covers design tokens, typography, product identity, shared chrome, and every page: product screens, all seven auth screens, and the marketing Welcome page.

This is a presentation change. No routes, controllers, models, or Inertia props change.

## Decisions

| Decision | Choice |
|---|---|
| Palette | Warm sand surfaces, terracotta primary |
| Scope | Everything — tokens, shell, product pages, auth, Welcome, identity |
| Product name | **Bobby** |
| Typography | Fraunces (display) + Inter (UI) + JetBrains Mono (machine data) |
| Dark mode | Kept, fully designed as warm charcoal |

All three typefaces are confirmed available on Bunny Fonts, including Fraunces italic.

## 1. Design Tokens

Rewrite the `@theme`, `:root`, and `.dark` blocks in `resources/css/app.css`. Variable **names stay identical**, so every shadcn component picks up the new palette without being touched. This is the leverage point of the whole redesign.

### Light

| Token | Value | Role |
|---|---|---|
| `--background` | `oklch(0.972 0.006 84)` | warm sand page and sidebar base |
| `--card`, `--popover` | `oklch(0.995 0.003 84)` | warm-white surfaces |
| `--card-foreground`, `--popover-foreground` | `oklch(0.22 0.012 55)` | |
| `--border`, `--input` | `oklch(0.905 0.008 78)` | warm stone hairlines |
| `--foreground` | `oklch(0.22 0.012 55)` | warm near-black |
| `--muted-foreground` | `oklch(0.53 0.014 60)` | secondary copy |
| `--muted`, `--secondary`, `--accent` | `oklch(0.945 0.008 80)` | hovers, chips, inset panels |
| `--secondary-foreground`, `--accent-foreground` | `oklch(0.22 0.012 55)` | |
| `--primary` | `oklch(0.55 0.13 38)` | terracotta |
| `--primary-foreground` | `oklch(0.985 0.006 80)` | |
| `--destructive` | `oklch(0.52 0.17 27)` | |
| `--ring` | `oklch(0.55 0.13 38 / 45%)` | terracotta focus ring |

Sidebar tokens track the same family: `--sidebar` sits at `oklch(0.958 0.008 82)` — one step deeper than `--background` so the inset content card reads as lifted — with `--sidebar-accent` at `oklch(0.925 0.012 76)` and `--sidebar-primary` terracotta.

### Dark — warm charcoal

Brown-tinted throughout; never neutral gray. A neutral dark theme under a warm light theme is the most common way this kind of redesign half-lands.

| Token | Value |
|---|---|
| `--background` | `oklch(0.19 0.008 55)` |
| `--card`, `--popover` | `oklch(0.225 0.009 55)` |
| `--border`, `--input` | `oklch(0.30 0.01 55)` |
| `--foreground` | `oklch(0.955 0.006 80)` |
| `--muted-foreground` | `oklch(0.70 0.012 70)` |
| `--muted`, `--secondary`, `--accent` | `oklch(0.27 0.01 55)` |
| `--primary` | `oklch(0.68 0.13 40)` |
| `--primary-foreground` | `oklch(0.20 0.02 45)` |
| `--sidebar` | `oklch(0.165 0.008 55)` |

### Semantic status colours

The current kit carries four chromatic signals: a neutral primary plus `--agent` (violet, live work), `--gate` (amber, blocked on approval), `--verified` (green, published). Violet clashes with a warm palette and duplicates what the brand colour should be saying.

Collapse to three hues total:

- `--agent` — **folded into terracotta primary.** Live agent work is the brand colour.
- `--gate` — honey `oklch(0.66 0.13 68)` light / `oklch(0.78 0.13 72)` dark
- `--verified` — sage `oklch(0.56 0.09 152)` light / `oklch(0.70 0.09 155)` dark

`--agent-muted` becomes the primary hue at 12% (light) / 16% (dark). The `--agent*` token names are retained so existing consumers in `resources/js/components/agent/*` keep compiling; only their values change.

### Shape and depth

- `--radius`: `0.625rem` → `0.75rem`.
- Warm-tinted shadow scale added to `@theme`, built on `oklch(0.35 0.03 55 / …)` rather than black. Cold black shadows over warm surfaces are the giveaway that a palette was swapped without retuning depth.
- `--chart-1` through `--chart-5` get a warm sequential ramp (terracotta → clay → honey → sage → stone) replacing the current unrelated rainbow.

### Pre-hydration background

`resources/views/app.blade.php` lines 23–31 hardcode `oklch(1 0 0)` and `oklch(0.145 0 0)` in an inline `<style>` to paint the html background before CSS loads. These must be updated to the new `--background` values for light and dark. Missing this makes every page flash pure white (or neutral black) before hydration.

## 2. Typography

Register all three families via Bunny in `vite.config.ts`, replacing the single `Instrument Sans` entry:

- `Fraunces` — weights 400, 500, 600 plus 400 italic
- `Inter` — weights 400, 500, 600
- `JetBrains Mono` — weight 400, 500

Add `--font-serif` and `--font-mono` to `@theme` alongside the retargeted `--font-sans` (Inter).

### Application

| Role | Family |
|---|---|
| Page titles, section headings | Fraunces 500, `letter-spacing: -0.01em` |
| Empty-state and hero copy | Fraunces 400 **italic** |
| UI chrome, labels, buttons, body, form text | Inter |
| Timestamps, run ids, step names, token counts, durations | JetBrains Mono, 11–12px |

The Fraunces italic empty-state treatment is the single place personality is allowed to surface. Everywhere else the serif stays restrained.

`resources/js/components/heading.tsx` becomes the enforcement point for display type — pages use it rather than applying `font-serif` ad hoc, so the serif can't drift across the app.

## 3. Identity — Bobby

- **`app-logo-icon.tsx`**: replace the Laravel bird path with a Bobby monogram — a rounded lowercase "b" in a squircle, drawn in the terracotta primary with a warm-white counter.
- **`app-logo.tsx`**: the mark's container currently uses `bg-sidebar-primary` with `text-white dark:text-black`. Those hardcoded black/white overrides are removed in favour of tokens.
- **Product name**: set `APP_NAME=Bobby` so `config('app.name')` drives both the sidebar label (via the `name` Inertia prop) and the `<title>` tag. No hardcoded string in the component.
- **`public/favicon.svg`**: regenerate in the same mark. `public/favicon.ico` and `apple-touch-icon.png` are replaced with renders of the new mark.
- **Sidebar footer**: `app-sidebar.tsx` lines 47–58 define `footerNavItems` linking to `github.com/laravel/react-starter-kit` and the Laravel starter-kit docs. Remove both; the sidebar footer becomes team switcher and user menu only, and `NavFooter` is dropped from `AppSidebar`.

## 4. Shared Chrome

Restyled in place, not rebuilt. Structure and props stay; classes change.

| Component | Change |
|---|---|
| `ui/sidebar.tsx` | warm sand ground, terracotta active pill with subtle tint fill, hairline separators instead of heavy borders |
| `ui/button.tsx` | 0.75rem radius, warm shadow on raised variants, terracotta primary, warm-tinted ghost hover |
| `ui/card.tsx` | hairline border plus warm shadow, replacing the current flat unshadowed box |
| `ui/badge.tsx` | gains `gate`, `verified`, and `live` variants so status colour is expressed through the variant API and never inline |
| `ui/input.tsx`, `ui/select.tsx` | warm focus ring, warm-white field on sand |
| `heading.tsx` | Fraunces display type, muted subtitle |
| `app-sidebar-header.tsx` | currently 18 lines holding only a collapse toggle on a bare bar. Gains breadcrumbs, a right-aligned page-actions slot, and a proper bottom border. |

## 5. Pages

Recolouring alone leaves the layout problems in place, so these get structural work too.

**Studio board** (`pages/studio/index.tsx`, `components/agent/board-column.tsx`, `post-card.tsx`, `empty-state.tsx`)
Columns become fixed-height-header + scrolling body rather than full-viewport dashed rectangles. Each column gets a warm-tinted background, a title with a count chip, and a per-column empty state in Fraunces italic. Cards gain hierarchy: excerpt in Inter, status badge using the new variants, relative time in mono.

**Dashboard** (`pages/dashboard.tsx`)
Currently 52 lines of placeholder pattern boxes. Replaced with a real overview built from existing props: recent run activity, posts awaiting approval, connected-account health.

**Post workspace, Runs index and show, Accounts, Brand, Teams index and edit, Settings**
Consistent page header (Fraunces title, muted subtitle, right-aligned actions), card-sectioned content, mono for machine data. `teams/edit.tsx` at 395 lines and `posts/show.tsx` at 200 lines are the two largest; sections within them are extracted into local components where a section exceeds roughly 80 lines, so the files stay readable.

**Auth — seven pages**
`layouts/auth-layout.tsx` currently delegates to `auth-simple-layout` (a bare centred card). Switch it to `auth/auth-split-layout`, which already exists, and restyle that layout: warm sand panel carrying the Bobby mark and a line of product copy on one side, form card on the other.

**Welcome** (`pages/welcome.tsx`, 390 lines)
Rewritten as a real marketing page: warm hero, a section explaining what the agent actually does, and an honest screenshot of the board. Sections are extracted into local components rather than left as one long file.

## 6. Verification

No new tests. This changes presentation, not behaviour, and the existing feature tests are Inertia render assertions — they already fail if a page component or its props break.

Verification gate, all of which must pass:

1. `php artisan test --compact` — full suite
2. `npx tsc --noEmit`
3. `npm run lint:check`
4. `npm run build`
5. `vendor/bin/pint --dirty --format agent`
6. Playwright: visit every page and screenshot in **both** light and dark, confirming the dark theme is tuned rather than inverted.

## Out of Scope

- Backend changes of any kind — routes, controllers, models, migrations, agent logic.
- New product features or new pages.
- Dependency changes. Everything is achievable with the installed Tailwind v4, shadcn components, and lucide-react.
- Refactoring unrelated to the surfaces being restyled.
