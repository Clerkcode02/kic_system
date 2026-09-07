# Design

<!-- impeccable:design-schema 1 -->

## Scope

This DESIGN.md currently records **one world**: **Trade Docket**, built for the
freelancer area (`/freelancer/*` — nav shell, dashboard home, proposals, contracts,
earnings). No other surface in this repo has an established visual world yet; the rest
of the app is unstyled stock Tailwind. Do not apply Trade Docket tokens outside the
freelancer feature without a deliberate decision to extend the world there.

## World: Trade Docket

**Thesis:** the freelancer's business run like a job-ticket counter — every contract is
a docket, every status is a stamp.

**Origin:** shaped via `/impeccable shape freelancer dashboard`, direction seed key
`6cb83a9d`, assigned direction index 6, raised with five donations from that round's
declined challengers (studio-dumbar-identity, pop-culture-shelf-doujin-event-catalog,
operate-c-emission-line-rail, signals-instruments-phosphor-terminal-midnight,
paper-folds-pleats-deployable-orizuru-crane-sequence).

**Scene:** a Canadian freelancer glancing at this between other work, on a phone or
laptop, in ordinary daylight — legibility over mood, light ground, no dark mode.

## Color

Full palette, named roles (not restrained) — status legibility across 5 milestone
states / 3 contract states / 5 proposal states is the point of this surface.

| Token | Hex | Use |
|---|---|---|
| `docket-paper` | `#EDE4D2` | Page/panel ground (kraft ticket stock) |
| `docket-well` | `#E2D5B8` | Recessed surfaces: nav bar, table header |
| `docket-ink` | `#221C14` | Primary text |
| `docket-soft` | `#5C5442` | Secondary text, field labels |
| `docket-rule` | `#C7B996` | Hairline dividers |
| `docket-line` | `#A6976F` | Borders |
| `action` / `action-hover` / `action-tint` | `#123B33` / `#0B2A24` / `#DFE9E6` | Primary interactive ink (buttons, active nav, links) |
| `stamp-paid` / `-tint` | `#146B3D` / `#E1F1E6` | Settled/positive status (paid, hired, completed) |
| `stamp-awaiting` / `-tint` | `#8A5A00` / `#FAECD6` | Provisional/in-progress-toward-money (approved-not-yet-paid, shortlisted, in escrow) |
| `stamp-active` / `-tint` | `#154E8C` / `#E1EDF7` | In motion (submitted, active contract) |
| `stamp-disputed` / `-tint` | `#9A2E22` / `#FAE6E1` | Needs attention (disputed, rejected, terminated) |
| `stamp-neutral` / `-tint` | `#54503F` / `#E9E2CE` | Inactive (pending, withdrawn) |

All tokens live in `web/tailwind.config.js` under `theme.extend.colors`. Stamp roles are
never the only signal: each also carries a distinct border pattern (see Components) and
its label is always present as text.

## Type

- Body/UI: Tailwind's default system-ui stack (no named display face — Operate mode,
  nothing about this subject earns one).
- Money, timestamps, IDs: `font-mono` (Tailwind's default system monospace stack) with
  `tabular-nums` — functional tabular alignment, not a "technical" costume.
- Field labels / section headers: `font-mono text-xs font-bold uppercase tracking-widest
  text-docket-soft` — a ledger-line label, not a marketing eyebrow.

## Components

- **`StatusStamp`** (`web/src/features/freelance/components/StatusStamp.tsx`) — the
  status mark. Role → color (see table) plus a distinct border style per role: `paid`
  solid heavy, `awaiting` dashed, `active` solid, `disputed` double, `neutral` dotted.
  Rotated -2°, animates in with `animate-stamp-land` (a rubber-stamp landing:
  scale+rotate settle, 260ms). Pass `animate={false}` when many stamps mount in one
  dense list (avoids a scattered simultaneous-animation moment).
- **`MoneyFigure`** — wraps `formatMoney` in `font-mono tabular-nums`.
- **`Perforation`** — a punched tear-perforation divider (`.docket-perforation` in
  `index.css`); only reads correctly directly on `bg-docket-paper`.
- **`Card` `variant="ticket"`** (shared `web/src/components/Card.tsx`, additive —
  default variant untouched) — kraft paper panel, `docket-line` border, `shadow-ticket`.
- **`Button` `variant="ink"`** (shared, additive) — the primary action ink stamp:
  `bg-action` / `hover:bg-action-hover`.
- **`Table` `variant="ledger"`** (shared, additive) — statement styling: `docket-well`
  header, `docket-rule` dividers, `docket-paper` body.
- **`MilestoneTimeline`** (`contract/components/MilestoneTimeline.tsx`) — the signature
  interaction: a milestone's full stage pipeline (`Pending → Submitted → Approved →
  Paid`) always renders, not just the current status, with passed stages solid-filled
  and a disputed milestone breaking the chain at its stage in `stamp-disputed`. History
  never collapses to a single current badge.

## Browser surfaces

Scoped under `.docket-scope` (the `FreelancerDashboard` root wrapper): themed text
selection, scrollbar, checkbox/file-input accent color, and focus ring color — all from
the palette rather than shipping browser defaults.

## Known gap — no visual finish review yet

This world was built code-led (no image generation available in the build environment)
and **no live-browser inspection round has been run against it** — this session had
no backend/auth session and no headless browser available to screenshot the actual
render. Typecheck, lint, and the existing unit test suite all pass, and contrast ratios
were checked arithmetically against WCAG 2.1 AA (all combinations above clear 4.5:1),
but nothing here has been visually confirmed at real breakpoints. Before treating this
as shipped: run `npm run dev`, sign in as an approved freelancer, and check
`/freelancer`, `/freelancer/proposals`, `/freelancer/contracts/:id`, and
`/freelancer/earnings` at desktop and mobile widths — particularly the milestone
timeline's wrapping behavior on narrow screens and the stamp animation on a dense
proposals list.
