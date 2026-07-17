# Cabin Inventory + Per-Person Pricing (Form & Invoice)

> Mirror to `wp-content/docs/plans/2026-07-14-cabin-inventory.md` on implementation (project rule).

## Context

Boat has 6 cabins. Three gaps: (1) admin can only set a coarse trip status (available/filling/booked) — no way to record how many cabins are already booked per date, and the frontend can't show "X of 6 cabins left"; (2) while filling the booking form the guest never sees the per-person rate of the suggested cabin plans, only totals; (3) the invoice shows one lump summary line — no per-person rate per cabin. All data needed already exists at calc time (`bhela_bm_calc_multi()` computes `$rate` per cabin) — it's just not surfaced or persisted.

## Changes (all in `plugins/bhela-booking`)

### 1. Per-trip cabin inventory — `includes/trips.php`

- Trip array gains `booked` (int 0–6, cabins already taken). Default 0; sanitized `min( bhela_bm_max_cabins(), max( 0, (int) ) )`.
- Admin Trip Calendar table: new "Booked cabins" number column (`<input type="number" min="0" max="6">`) + read-only hint per row showing auto-counted cabins from actual bookings on that date (status `advance_paid|confirmed`, count rows of `_bhela_cabins_json`) so admin can reconcile.
- Helper `bhela_bm_trip_availability( $date )` → `array( 'total' => 6, 'booked' => n, 'available' => 6-n, 'trip' => $t|null )`. If `booked >= 6` treat status as `booked` regardless of manual status.
- `[bhela_trip_calendar]` shortcode: availability chip per row — "🛏️ Xটি কেবিন খালি" (green >2, amber 1–2, red 0 → shows বুকড, CTA off).

### 2. Availability AJAX + form — `includes/frontend.php`, `assets/booking.js`

- `bhela_bm_ajax_availability()` response gains `total/booked/available` from the helper.
- booking.js step-1 availability box renders "✅ Available — ৬টির মধ্যে ৪টি কেবিন খালি" (or amber/red variants); `available === 0` behaves like current 'booked' path (blocked box + WhatsApp link).
- Client caps: auto-plan generator and manual cabin editor limit cabin count to `available` (new field piped through `bhelaBM` via availability response; store on state after check). Server is authoritative (below).
- **Server validation** in `bhela_bm_process_submission()`: `count($lines) <= available` for the chosen date, else `WP_Error` "এই তারিখে মাত্র Xটি কেবিন খালি"।

### 3. Per-person price during form fill — `assets/booking.js`

- Auto-plan option cards: append rate line — "৳8,000/জন (বড়)" using `occRates` already localized in `bhelaBM`.
- Price rail breakdown rows: per cabin show "কেবিন (৪ জন) — ৳8,000/জন × …" instead of amount-only. Data already client-side; render change only.

### 4. Per-person on invoice — `includes/frontend.php`, `includes/invoice.php`, `templates/invoice.php`

- `bhela_bm_calc_multi()`: each `$lines[]` entry gains `'rate' => $rate`, `'occ' => $occ`, `'adults'`, `'c48'`, `'amount' => $line`.
- Save: `update_post_meta( $post_id, '_bhela_lines', wp_json_encode( $price['lines'], JSON_UNESCAPED_UNICODE ) )`; also store real `_bhela_per_person` = weighted average or leave 0 and rely on lines (choose: keep 0, lines are the source).
- `bhela_bm_maybe_render_invoice()`: add `'lines' => json_decode( $m('_bhela_lines'), true ) ?: array()` to `$invoice`.
- `templates/invoice.php`: if lines exist render an items table — columns: কেবিন | অতিথি | রেট/জন | মোট — one row per cabin (children 4–8 shown at ৫০% in the রেট cell, e.g. "৳8,000/জন · শিশু ৫০%"); falls back to current summary line for old bookings/full-boat.
- Customer email (`emails.php` HTML) reuses the same lines table if present (same fallback).

### 5. Version + docs

- Plugin `2.4.1 → 2.5.0` (feature). Mirror plan into `wp-content/docs/plans/`. `graphify update .`

## Reuse

- `bhela_bm_max_cabins()` (=6), `bhela_bm_money()`, `bhela_bm_get_trips()` sanitize pattern (trips.php:70-94), `bhelaBM.occRates` already localized (frontend.php:29-36), `check_ajax_referer` guards.

## Verification

1. Admin → Trip Calendar: set a date's booked=4 → save → reload shows 4; frontend calendar shows "২টি কেবিন খালি"।
2. Booking form: pick that date → availability box shows ২টি খালি; auto-plan never proposes >2 cabins; manual editor add-cabin disabled at 2.
3. Force-submit 3 cabins via curl (crafted cabins JSON, valid nonce) → server rejects with the Bangla error.
4. Option cards + breakdown show ৳X/জন rates (browser check).
5. Submit a 2-cabin booking (different occupancies) → invoice URL shows items table with two rows, correct rates (weekday vs weekend date both tested), totals unchanged vs before.
6. Old booking (33/35) invoice still renders (fallback path). Regression: booking flow end-to-end, tracker, emails log in Mailpit.
