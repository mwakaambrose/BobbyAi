# Landing Page Redesign — Bobby

**Date:** 2026-08-04
**Status:** Built. Amended during implementation — see *Changes made while building*.

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
| Angle | The outcome: "Show up on LinkedIn every week. Without writing every week." |
| Colour | Semantic agent colours only — state chips and the hero motif |
| Imagery | One wide photo band between the hero and the beats |
| Hero ground | Dot field with sparse agent/gate/verified marks over a faint tint |
| Files touched | `resources/js/pages/welcome.tsx`, `public/images/hero-desk.jpg` |

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

- Eyebrow (mono, uppercase, tracked): `AN AI AGENT FOR LINKEDIN`
- H1 (Fraunces): **Show up on LinkedIn every week. Without writing every week.**
- Sub: "Bobby learns your voice, drafts from a one-line brief, and publishes the moment you approve. Nothing goes out unread."
- CTAs unchanged in destination: `Start writing` (primary → register), `Log in` (outline → login).
- Attribute row beneath the CTAs, mono and rule-separated: `ONE-LINE BRIEF → THREE DRAFTS` · `PUBLISHES ITSELF` · `NEVER WITHOUT YOUR APPROVAL`.

The headline carries the outcome — cadence and the time it does not cost. The agent, its intelligence and the approval gate all still appear, but in the eyebrow, the subhead and the attribute row rather than competing for the headline. The attribute row is what gives the hero weight: three checkable facts instead of adjectives.

No invented numbers. Nothing of the "save five hours a week" kind, because nothing in the product measures it.

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
- Shadows only where a surface should read as paper; `shadow-sm`/`shadow-md` at most. The page is mostly flat.

### Hero ground — the agent motif

The hero sits on a pattern rather than a wash. A plain gradient was tried first and rejected: its falloff read as a visible edge across the page.

- An even dot field in the hairline border colour, `26px` pitch, at `70%` opacity.
- Seven larger marks scattered through it in the semantic colours — `●` drafting, `◆` waiting, `✓` published — reading as posts caught mid-flight: some being written, some held, some already out. The `●` marks pulse; nothing else moves.
- Marks sit **only in the margins**, outside the centred text column, so none can ever land behind the headline. They are hidden below `lg`, where there is no margin to place them in.
- Underneath, a much fainter version of the warm tint — present as tone, not as a visible gradient.
- The whole field is masked to dissolve by `45%` of its height, so it has no bottom edge of its own.

### Imagery

One photo, as a wide band between the hero and the beats — never behind the hero type, and never a split-hero layout, both of which would undo the centred display setting.

- Pexels 10376030 by RDNE Stock project, under the Pexels licence: free for commercial use, attribution not required. Credited in a code comment regardless.
- Self-hosted at `public/images/hero-desk.jpg` rather than hotlinked from the Pexels CDN.
- Chosen for warm wood and skin tones that sit inside the sand/terracotta palette, and for having no face — so it neither casts a persona for the user nor dates the page.
- Letterboxed (`h-56` / `sm:h-72` / `lg:h-80`), `object-cover`, desaturated to `0.88`, and faded into the page background at top and bottom so it joins the paper instead of sitting on it as a rectangle.
- Dark mode needs its own treatment: at light-mode values the band glows as a bright block against the dark ground, so it drops to `opacity-70`, `saturate-0.6`, and a much heavier edge fade.
- `loading="lazy"` with explicit `width`/`height`, since it sits below the fold.

### Colour

Colour is limited to the primary buttons and the three semantic states, which appear twice: as the marks in the hero motif, and as one small mono chip per beat.

| Beat | Chip | Token |
|---|---|---|
| 01 Write | `● drafting` | `--color-agent` |
| 02 Push back | `◆ waiting for you` | `--color-gate` |
| 03 Watch | `✓ published` | `--color-verified` |

These are the same three states the console uses, so the landing page teaches the product's vocabulary before you sign up. Restraint is the point — if colour appears anywhere else, the chips stop reading as meaningful.

No animation beyond what the tokens already imply. The `drafting` marks and chip pulse; nothing else moves.

### Dark mode

Every token used is already defined for both themes, so most of the page inverts on its own. Two things do not and need explicit dark variants: the photo band, which is too bright at light-mode values, and any tint behind the hero, which must be expressed in token terms rather than as a hardcoded light-mode wash.

## 3. Component structure

`welcome.tsx` keeps its current shape — local function components in one file, since nothing here is reused elsewhere:

- `Rule` — new; the full-bleed hairline used between movements.
- `Motif` — new; the hero ground, driven by a `marks` const array of `{ edge, offset, top, mark, tone }`.
- `Nav` — unchanged behaviour (auth-aware Dashboard vs Log in / Register), restyled to sit on the new hairline rhythm. The register CTA is relabelled `Start writing` to match the hero, and log in drops to `ghost` so there is one primary action per view.
- `Hero` — new copy and type treatment, plus the attribute row from an `attributes` const array.
- `Band` — new; the photo.
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

- Adding sections beyond the closing block and the photo band (no pricing, testimonials, FAQ, product screenshot).
- Any change to `app.css` tokens.
- Changes to auth pages or console pages.
- New dependencies or fonts.

## Changes made while building

The original design was approved with a different hero and no imagery. Three amendments were made in review, each recorded here so the document matches what shipped:

1. **Hero re-angled.** The approved headline led with the approval gate ("It writes. It waits. You decide."). It was replaced with the outcome-led line, to foreground the AI agent, its intelligence, the time it saves and the posting cadence. The gate did not disappear — it moved to the subhead, the third attribute and the closing line.
2. **Photo band added.** Not in the approved design, which explicitly excluded imagery.
3. **Gradient replaced by the motif.** The approved hero ground was a radial wash. Its falloff read as a visible edge, so it became the dot field with agent marks.

Two defects were found only by rendering the page in a browser, and neither was predictable from the markup:

- The hero wash showed a hard rectangular edge because it was scoped to the hero section; it had to move to the page root to run up behind the nav.
- The beat chips sat at ragged heights because the step bodies differ in length; they are pinned to a shared baseline with `mt-auto`.

A third was caught by luck: the Vite dev server died mid-session, and Laravel silently fell back to a stale build, so one verification screenshot showed the pre-redesign page. Verify against a fresh `npm run build` when the dev server is not confirmed running.
