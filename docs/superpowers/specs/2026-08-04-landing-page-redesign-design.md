# Landing Page Redesign — Bobby

**Date:** 2026-08-04
**Status:** Approved design, ready for implementation planning

## Problem

`resources/js/pages/welcome.tsx` is the front door, and it ignores the design language the rest of the app runs on. It renders in Outfit throughout, with no `font-display`, no mono eyebrows outside a single line, and default shadcn `Card` chrome for the three-step section. The warm-UI work established Fraunces for display type, Geist Mono for machine data, paper-layer shadows, and three semantic agent colours — the landing page uses none of them.

The copy has a second problem. It pitches Bobby as an agent that writes posts. Every AI tool writes posts. The page never mentions the things the product actually does that nothing else does: three alternatives per brief, a conversation about a draft, single-fragment rewrites that leave approved text untouched, and a real browser run you can watch by screenshot and abort mid-flight.

## Goal

Give the page visual character drawn from the tokens already in `app.css`, and re-pitch it from a sharper angle using only claims the app can back. Structure stays close to what exists: nav, hero, three beats, closing, footer.

This is a presentation change. No routes, controllers, models, or Inertia props change. No design tokens change. No dependencies change.

## Decisions

| Decision | Choice |
|---|---|
| Aesthetic | Editorial paper — Fraunces display, hairline rules, mono labels |
| Copy scope | New angle, same facts |
| Angle | The gate, not the writing: "It writes. It waits. You decide." |
| Colour | One restrained use — three semantic state chips |
| Files touched | `resources/js/pages/welcome.tsx` only |

## Product facts the copy may claim

Confirmed against routes and controllers, so the rewrite stays honest:

- A brief produces a post with **three alternatives** (`posts.alternatives.store`).
- You can **talk to the draft** (`posts.messages.store`).
- You can have **one fragment rewritten in place** (`posts.refinements.store`, `posts.refinements.accept`).
- Nothing publishes until **explicit approval** (`posts.approve`).
- Publishing is a **real browser run** with per-step **screenshots** (`runs.screenshot`) that you can **abort** (`runs.abort`).
- Voice comes from a **brand profile** (`brand.edit`).

Anything outside this list is out of bounds for the page.

## 1. Copy

### Hero

- Eyebrow (mono, uppercase, tracked): `A LINKEDIN AGENT THAT ASKS FIRST`
- H1 (Fraunces): **It writes. It waits. You decide.**
- Sub: "Bobby drafts in your voice and shows its work. Nothing reaches LinkedIn until you say the word."
- CTAs unchanged in destination: `Start writing` (primary → register), `Log in` (outline → login).

The three-beat headline sets up the three-column section below it, and maps onto the three state chips. That rhyme is the structural idea of the page.

### Three beats

Replaces the current `How it works` heading and cards. The section keeps a heading, set smaller than the hero so the hero stays dominant.

| # | Title | Body |
|---|---|---|
| 01 | Write | Give it a topic, a link, or a half-formed thought. It comes back with three drafts in your voice, not one take you have to argue with. |
| 02 | Push back | Tell it the second line is too much and it rewrites that line, in place. Everything you already approved stays untouched. |
| 03 | Watch | Say yes and a real browser session publishes it, step by step, with screenshots. You can watch it happen. You can stop it mid-run. |

### Closing

The page currently stops dead after step 03 and falls into the footer. Add a closing block:

- Line (Fraunces, smaller than H1): **No autopilot. Nothing on your feed you didn't read first.**
- Repeat primary CTA: `Start writing`.

Footer copy unchanged.

## 2. Visual treatment

### Typography

| Element | Treatment |
|---|---|
| H1 | `font-display`, ~`text-6xl` at `sm`, tight tracking, `text-balance` |
| Closing line | `font-display`, roughly half the H1 size |
| Section heading | `font-display`, smaller again — the hero must stay the loudest thing |
| Eyebrow, step numbers, state chips | `font-mono`, uppercase, widely tracked, `text-label` size |
| Body, sub, step bodies | `font-sans`, muted foreground |

### Structure

- **Cards out.** The three beats become columns separated by hairline rules (`border-rule`) rather than `Card` borders and padding. On mobile the vertical dividers become horizontal ones between stacked beats.
- **Section separation** by full-bleed hairline rules, not guessed padding.
- **Hero air.** More vertical space above and below the hero than the current `pt-20 pb-16`, so the display type has room.
- A soft radial lift behind the hero, low opacity, built from existing background/foreground tokens — no new colour values.
- Shadows only where a surface should read as paper; `shadow-sm`/`shadow-md` at most. The page is mostly flat.

### Colour

The page is otherwise monochrome. The only colour is one small mono state chip per beat:

| Beat | Chip | Token |
|---|---|---|
| 01 Write | `● drafting` | `--color-agent` |
| 02 Push back | `◆ waiting for you` | `--color-gate` |
| 03 Watch | `✓ published` | `--color-verified` |

These are the same three states the console uses, so the landing page teaches the product's vocabulary before you sign up. Restraint is the point — if colour appears anywhere else, the chips stop reading as meaningful.

No animation beyond what the tokens already imply. The `drafting` chip may use a subtle pulse; nothing else moves.

### Dark mode

Every token used is already defined for both themes, so dark mode follows automatically. The radial hero lift must be expressed in token terms so it inverts correctly rather than being a hardcoded light-mode wash.

## 3. Component structure

`welcome.tsx` keeps its current shape — local function components in one file, since nothing here is reused elsewhere:

- `Nav` — unchanged behaviour (auth-aware Dashboard vs Log in / Register), restyled to sit on the new hairline rhythm.
- `Hero` — new copy and type treatment.
- `Beats` — renamed from `HowItWorks`; consumes a `beats` const array extended with `chip` (label + token class) alongside `number`, `title`, `body`.
- `Closing` — new.
- `Footer` — unchanged.

The `Card` import is dropped once the beats stop using it.

## 4. Testing

A feature test in `tests/Feature/` asserting the landing page renders:

- `GET /` returns 200 and the `welcome` Inertia component for a guest.
- `GET /` returns 200 for an authenticated user with a current team, covering the `Nav` branch that builds a dashboard URL from `currentTeam`.

Run with `php artisan test --compact --filter=<name>`. This is a smoke test — it protects the auth-aware nav branch, which is the only logic on the page. Visual treatment is not asserted.

## Out of scope

- Adding sections beyond the closing block (no pricing, testimonials, FAQ, product screenshot).
- Any change to `app.css` tokens.
- Changes to auth pages or console pages.
- New dependencies or fonts.
