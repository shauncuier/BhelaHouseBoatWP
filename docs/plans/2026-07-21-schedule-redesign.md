# Trip Schedule page — premium redesign

> Project-based plan (`wp-content/docs/plans/`). Implemented 2026-07-21 — theme v2.12.1 / plugin v2.8.1.

## Context

`/schedule/` is the page that turns interest into a booking, and it currently looks like a plain admin table dropped onto a premium site. Three separate problems:

**It has a live bug.** Today is 21 Jul 2026, yet the trip of **15–16 Jul** still renders as “Available ✅ / 3টি কেবিন খালি” with a working **বুক করুন** button pointing at `?date=2026-07-15`. The booking form filters past trips (`includes/frontend.php:91`) but `bhela_bm_trip_calendar_shortcode()` never does — a guest can start booking a departed trip.

**The layout is broken by a CSS mismatch.** `booking.css:387` declares `grid-template-columns: 1.4fr 1.2fr auto auto` — four columns — but the shortcode emits **five** children (date, meta, status, cabins, cta), so the CTA silently wraps onto an implicit second row on desktop. `.bhela-trip__cabins` has **no CSS rule at all**, and status/availability colours are written as inline `style="color:#…"`, so the theme's tokens cannot reach them. The result diverges visually from the rest of the site — the theme contains **zero** `.bhela-trip*` rules.

**It under-sells.** 14 trips render as one flat, ungrouped list. Every card shows a “Weekday −20% 🔥” badge but never the actual price, even though the rate and day type are both known server-side.

Owner's choices: **premium card grid**, **per-trip price**, **month grouping**, **low-availability urgency**.

## Design decisions

Rebuild the shortcode's output and its stylesheet; the trip data model, admin screen and `bhela_bm_trip_availability()` stay exactly as they are.

- **Reuse the theme's existing card language** rather than inventing one: `.cabin-card` (`style.css:251-271`) is the site's premium card — `--radius`, `--shadow-sm`, `--line`, hover `translateY(-8px)` → `--shadow`. Mirror those tokens so the schedule finally matches the cabins page.
- **Keep the styles in `booking.css`** (the plugin must render standalone) but switch every hardcoded hex to `var(--token, fallback)` — the file already does this for `--cta` at `:404`. That lets the theme drive the palette while keeping the plugin self-contained.
- **Price per trip** from `bhela_bm_rates_by_occupancy()` (`bhela-booking.php:103`): the cheapest per-person figure is the largest sharing tier (6-share). Show `জনপ্রতি ৳৬,৪০০ থেকে` on weekday trips with the regular `৳৮,০০০` struck through, and the plain regular rate otherwise — turning the abstract “−20%” badge into a real number. Use `bhela_bm_money()` so the formatting matches invoices and the booking form.
- **Numerals stay English** (`৳6,400`, `3/6টি`) to match `bhela_bm_money()` and every other price surface on the site. Mixing Bangla digits here would be inconsistent, not more correct.
- **Urgency** is derived from the existing `$avail['available']`: ≤2 cabins gets a distinct “শেষ ২টি কেবিন!” treatment. No new data, no admin change.

## Changes

### `plugins/bhela-booking/includes/trips.php` — rewrite `bhela_bm_trip_calendar_shortcode()` (:214-258)
- **Skip past trips**: `if ( $t['date'] < current_time( 'Y-m-d' ) ) continue;` — mirrors the guard already at `frontend.php:91`. Use `current_time()`, not `date()`, so it follows the site timezone.
- **Group by month**: bucket the surviving trips by `date( 'Y-m' )` and emit a `<h3 class="bhela-trips__month">` heading per group (`date_i18n( 'F Y' )`).
- **Card markup** per trip — one self-contained card, no 5-children-in-4-columns trap:
  - date label + days
  - day-type chip (existing `--weekday` / `--holiday` logic at `:230-231`)
  - optional note
  - **price row**: “জনপ্রতি **৳6,400** থেকে” + struck regular on discounted days
  - availability chip + status
  - CTA (`বুক করুন →` / disabled `বুকড`)
- **Replace inline colours with modifier classes** — `.bhela-trip__status--available|filling|booked`, `.bhela-trip__cabins--low`. Keeps `bhela_bm_trip_statuses()` as the source of the *labels*, while the *colours* move into CSS where the theme can override them.
- **Highlight the next departure**: first card in the list gets `.bhela-trip--next` plus a small “পরবর্তী ট্রিপ” ribbon.
- **Styled empty state** replacing the bare `<p>` at `:218-220` — a centred card with the WhatsApp CTA (`bhela_wa_link()`), used both when there are no trips at all and when every remaining trip is in the past.

### `plugins/bhela-booking/assets/booking.css` — rewrite the trip block (:385-413)
- `.bhela-trips` → responsive card grid: `repeat(auto-fit, minmax(300px, 1fr))`, so 14 trips scan in 2–3 columns instead of 14 stacked rows. Month headings span the full grid row.
- `.bhela-trip` → column flex card (date → meta → price → availability → CTA), fixing the column/child mismatch by construction. Reuse `--radius`, `--shadow-sm`, `--line`, `--ease`; hover lift matching `.cabin-card:hover`.
- Status/availability colour classes; `.bhela-trip__cabins` finally gets real styling; `.bhela-trip--next` accent (`--gold`/`--cta` ribbon); `.bhela-trip--booked` keeps its muted treatment.
- `font-variant-numeric: tabular-nums` on dates and prices — the theme applies this to `.sched-table` (`style.css:910`) but the live markup never got it, so dates don't align.
- Responsive: 1 column below 560px; replace the `@media (max-width:760px)` grid-position hacks at `:409-413`, which exist only to patch the broken 4-column layout.
- Honour `prefers-reduced-motion` for the hover transform, as the theme does at `style.css:79-82`.

### `themes/bhela/page-templates/template-schedule.php`
- Add the page furniture the theme already styles but this page never used: `.eyebrow` and `.section-lead` (`style.css:92-100`), and a short summary line (“আগস্টে ১৩টি ট্রিপ”).
- Replace the bare closing sentence (`:57`) with a proper CTA card — “অন্য তারিখ বা Full Boat?” + `.btn--wa` — reusing existing button classes.
- **Delete the dead 13-row hardcoded `$trips` fallback table** (`:23-55`). It never renders while the plugin is active, and it hardcodes 2026 dates that will silently rot into wrong information. Replace with a one-line graceful message + WhatsApp link; schedule data belongs to the plugin.

### Versions
Bump `BHELA_BM_VERSION` + plugin header (`bhela-booking.php:5,16`) — `booking.css` is cache-busted by it — and `BHELA_VERSION` (`functions.php:12`) for the template change.

## Verification

1. **Lint**: `php -l` on the changed PHP files.
2. **Past-trip bug fixed**: `curl /schedule/` must contain **no** `?date=2026-07-15` and no `15 – 16 Jul` card, while the 13 future trips remain.
3. **New structure**: assert month headings appear, card count = 13, every card carries a price (`জনপ্রতি`), and **no `style="color:` remains** in the trip markup.
4. **Price correctness**: weekday cards show the discounted per-person rate with the regular struck; weekend/holiday cards show the regular rate — cross-check against `bhela_bm_rates_by_occupancy()`.
5. **Urgency**: temporarily set a trip's `booked` to 5 in `bhela_bm_trips`, confirm the low-availability class and message appear, then restore the original value.
6. **Empty/edge states**: with all trips in the past, the styled empty card renders instead of a blank section.
7. **Responsive**: 3 → 2 → 1 columns; confirm no horizontal overflow at 375px.
8. **Regression**: all 10 pages still 200; assets served at the new version; `graphify update .`.

## Notes

- Per standing instruction: **do not commit, push, tag or release** — stop after implementation and verification, then report.
- Browser screenshots of `.local` are blocked by per-action approval in this environment, so visual confirmation is by rendered-HTML assertions; the owner should eyeball the page once.
