# BHELA Booking Engine

Complete booking engine for **BHELA – The Haor Exclusive** houseboat. Cabin pricing, per-date cabin inventory, booking statuses, secure invoices, and email + SMS notifications.

- **Version:** 2.34.0
- **Requires:** WordPress 6.0+, PHP 8.0+
- **Pairs with:** the `bhela` theme (Midnight Monsoon). Works standalone; the theme adds the booking pages.

> **Owner-facing guide:** `wp-content/docs/BHELA-Owner-Manual.md` (styled version published as an Artifact).
> **Design/feature history:** `wp-content/docs/plans/`.

## Where things are in wp-admin

Four menus, grouped by the job you are doing. Each one is **hidden from anyone who cannot use
it**, so a storekeeper sees Store and nothing else.

| Menu | Rows |
| --- | --- |
| **Bookings** | All Bookings · Add New · 📊 Dashboard · 📄 Trip Report · 📅 Trip Calendar · ⭐ Reviews |
| **Accounts** | 🧾 Cost Sheets · 💸 Expenses · 👷 Salary · 📈 Monthly Statement · 📚 Yearly Report |
| **Store** | 📦 Item Register · 🚚 Import Register · 🔧 Monthly Stock · 📐 Inventory Report · 🏷️ Asset Report · 🔩 Audit Trail |
| **Setup** | ⚙️ Settings · 👥 Team · 🗺️ Spots · 🖼️ Gallery · ⬆️ Bulk Upload · 📋 Activity Log · 🎯 Quick Guide |

Clicking a menu's own name opens its first row: Bookings → All Bookings, Accounts → Cost Sheets,
Store → Item Register, Setup → Settings.

Everything used to live under **Bookings** as 22 rows. Old links of the form
`edit.php?post_type=bhela_booking&page=…` still work — a permanent redirect keeps them alive,
because URLs of that shape are already in email this plugin has sent.

## Features

- **Booking form** — shortcode `[bhela_booking_form]`: stepper wizard, live per-person pricing, auto cabin-plan, honeypot + per-IP throttle.
- **Trip calendar** — shortcode `[bhela_trip_calendar]`: admin-managed dates + per-date cabin inventory (X/6 available).
- **Reviews** — shortcode `[bhela_reviews]`.
- **Cabin inventory** — `booked` count per trip; frontend + form respect remaining cabins; server rejects over-capacity submits.
- **Invoices** — secure per-booking link (`wp_hash` + `hash_equals`), per-cabin per-person line items, bKash/Nagad/bank/QR. A settled invoice carries a **PAID / সম্পূর্ণ পরিশোধিত** stamp drawn as an outline, so it survives printing with backgrounds off; an unpriced ৳0 quote never gets one.
- **Inventory & Asset Register** — a permanent record per item, a monthly stock sheet whose opening is last month's closing, a physical count whose variance must be explained before the month can close, and a month that locks when closed. Plus a column-mapped CSV importer for the opening baseline, an Inventory and an Asset report, and an append-only Audit Trail recording who changed which figure from what to what. A closed month cannot be edited or deleted — not through the form, not through `update_post_meta()`, and not by an administrator.
- **Full Boat** — bookable from the form *and* creatable in wp-admin: takes all 6 cabins. From the form it arrives **priced at the standard whole-boat rate** (every cabin full, 6 × 6 = 36 people — ৳288,000 weekend, ৳230,400 weekday), which an admin then adjusts after negotiating; the form and the invoice both say the price is negotiable. It cannot be confirmed onto a date with any other cabin sold unless "Overbook" is ticked, and one created in wp-admin with no price warns rather than blocks.
- **Booking tracker** — customers look up status by phone/email (not the guessable invoice number).
- **Email notifications** — per-type toggles, owner recipient, From-name, Reply-To, test send.
- **SMS notifications** — provider-agnostic (BulkSMSBD preset + custom-gateway mapping), 3 triggers, test send. Off by default.

## Structure

| File | Responsibility |
| --- | --- |
| `bhela-booking.php` | Bootstrap, settings defaults (`bhela_bm_default_settings`), pricing engine, statuses, CPT |
| `includes/frontend.php` | Booking form, AJAX (submit / availability / track), submission processor |
| `includes/invoice.php` | Secure invoice links + rendering |
| `includes/emails.php` | Admin + customer emails, test-send |
| `includes/sms.php` | Provider-agnostic SMS sender, triggers, test-send |
| `includes/otp.php` | Mobile verification on the booking form — send/verify endpoints, throttles, email fallback |
| `includes/trips.php` | Trip calendar admin + shortcode + availability helper |
| `includes/reviews.php` | Reviews CPT |
| `includes/ui.php` | Shared admin UI — the screen header, the status pill, the booking-status tone map |
| `includes/admin.php` | Bookings columns, edit meta box, Settings page, dashboard widget |
| `includes/reports.php` | Trip Report screen — bookings for a date/range with advance, due and totals; print, WhatsApp text and CSV export |
| `includes/costs.php` | Trip Cost Sheet CPT + prepare/check/approve workflow, editable heads, printable sheet |
| `includes/expenses.php` | Expense CPT (advertising, renovation…), editable types and payment methods |
| `includes/statement.php` | Monthly Statement — approved trips less the month's expenses |
| `includes/yearly.php` | Yearly Report — twelve monthly statements, season totals, expense mix |
| `includes/salary.php` | Staff roster + monthly salary sheet |
| `includes/roles.php` | Staff roles, every plugin capability, and the read-only Team reference screen |
| `includes/audit.php` | The audit trail — the plugin's one database table, one insert-only writer, and a viewer with no clear button |
| `includes/inventory-core.php` | The two stock post types and the lock. **Loads on every request**, so a closed month is closed against WP-CLI too |
| `includes/inventory.php` | Stock lists, the quantity model, monthly periods and carry-forward, the close workflow, every register screen, CSV and the print view |
| `includes/inventory-import.php` | The four-step column-mapped CSV importer: upload, map, dry run, commit |
| `includes/menu.php` | The four menus: which group owns which page, `bhela_bm_admin_url()`, and the redirect that keeps old links alive |
| `assets/admin.css` | The admin design system — tokens, screen header, cards, ledger, pills, print |
| `templates/invoice.php` | Printable invoice |

## Key functions / extension points

- `bhela_bm_send_sms( $number, $message )` — send via the configured gateway.
- `bhela_bm_render_sms( $template, $booking_id )` — fill `{placeholders}` from a booking.
- `bhela_bm_trip_availability( $date )` → `total / booked / available / status`.
- `bhela_bm_report_rows( $from, $to, $with_cancelled )` → `rows / totals` for a travel-date range.
- `bhela_bm_cost_heads( $include_retired )` — the trip cost heads in force, slug => label.
- `bhela_bm_expense_rows( $from, $to )` → expenses in a range, totalled per type.
- `bhela_bm_statement_data( $month )` → a month's approved trips, deductions and gross profit.
- `bhela_bm_yearly_data( $year, $mode )` → twelve months rolled up, plus totals, margin and the year's expense mix. Built by calling the monthly statement twelve times rather than re-querying, so a yearly figure can never disagree with the month it summarises. `$mode` is `financial` (July–June, the default and what Bangladesh uses) or `calendar`.
- `bhela_bm_cost_transitions()` — the workflow state machine (from-state, target, required capability).
- `bhela_bm_roles()` — staff roles with their capabilities; the single source of truth the rest of the plugin reads.
- `bhela_bm_permissions()` — the togglable permission registry, and the allow-list for what the Team screen may grant.
- `bhela_bm_normalise_perms( $perms )` — drops unknown keys, pulls in prerequisites.
- `bhela_bm_calc_multi( $cabins, $date )` — authoritative per-cabin pricing.
- `bhela_bm_balance( $total, $paid )` → `total / paid / due / settled`. The one reading every guest-facing surface shares. `settled` requires a positive total, so an unpriced ৳0 quote is never treated as paid.
- `bhela_bm_booking_day_type( $booking_id )` — the day type derived from the booking's travel date. Always use this instead of reading `_bhela_day_type`, which is a cache and has been stale in production.
- `bhela_bm_full_boat_label()` — the `_bhela_cabin_type` string a whole-boat booking carries, so wp-admin and the booking form cannot drift.
- `bhela_bm_sanitize_weekend_days( $raw )` — `date('w')` numbers whitelisted to 0–6.
- `bhela_bm_invoice_data( $booking_id )` — everything `templates/invoice.php` needs, without a request that ends in `exit()`.
- `bhela_bm_screen_header( $icon, $title, $lead, $actions, $class )` — the banner every screen opens with.
- `bhela_bm_status_pill( $label, $tone, $solid )` — one pill for every status vocabulary in the plugin.
- `bhela_bm_is_plugin_screen()` — true on a BHELA admin screen; gates the stylesheet and the body class.
- Notifications fire from `bhela_bm_process_submission()` (new booking) and `bhela_bm_save_booking()` (status change).

## Admin design system

Everything visual lives in `assets/admin.css`, loaded only where `bhela_bm_is_plugin_screen()`
is true. It is scoped to `.bhela-admin`, which the plugin puts on `<body>` — not on the page
wrapper, because the cost sheet and salary sheet are meta boxes whose markup sits outside
anything we control.

**Tokens.** Chrome (`--bha-surface`, `--bha-canvas`, `--bha-line`, `--bha-text`, `--bha-muted`)
deliberately matches wp-admin's own greys rather than the website palette — a card floating on
the admin canvas needs WordPress's line colour or it reads as a foreign object. Brand
(`--bha-ink`, `--bha-teal`) appears at full strength in exactly one place: the header band.

**Five semantic tones**, named once and used everywhere:

| Token | Means |
| --- | --- |
| `--bha-neutral` | draft, none, not applicable |
| `--bha-progress` | in flight — prepared, checked, advance paid |
| `--bha-good` | settled — approved, confirmed, paid |
| `--bha-attention` | needs a person — pending, low balance |
| `--bha-danger` | money owed, failures, destructive |

Pills carry a second axis: **solid means the state has landed, soft means it is still moving.**
That is what lets five tones cover every vocabulary — booking status, cost-sheet workflow, trip
availability — without inventing a sixth hue. The activity log is the one exception: its nine
channels are a taxonomy rather than a status, so they use the `.bha-tag--*` set instead.

**Adding a screen.** Open with `bhela_bm_screen_header()`, wrap the page in
`<div class="wrap bha-page">`, and build from the existing classes — `.bha-bar` for filters,
`.bha-cards` for summary figures, `.bha-panel` for a section, `.bha-num` for every money column,
`.bha-callout--attention` for something the reader has to act on. Do not add an inline
`<style>` block or a fifteenth class prefix; if a genuinely new component is needed, name it
`bha-` and put it in `admin.css`. Every figure goes through `bhela_bm_money()`, including print
views — the screen and the printout used to disagree on the same sheet.

## Mobile verification (OTP)

`Setup → ⚙️ Settings → SMS`. Off by default. When on, a booking cannot be submitted until the guest enters a code sent to the number they typed.

Message format, deliberately short: `Your BHELA OTP is 4821` — 22 characters, one SMS part.

**Why the brand is its own setting.** One character outside GSM-7 flips an SMS to Unicode encoding, which drops the segment from 160 characters to 70 and doubles the cost of every code sent. `business_name` is `BHELA – The Haor Exclusive`, and that en-dash is exactly such a character. `otp_brand` is separate and pushed through `bhela_bm_otp_gsm_safe()` on save, which folds smart punctuation to ASCII and strips anything else.

**Fallback.** SMS first; if the gateway fails — no balance, unreachable, sender ID rejected — the code is emailed. Email is optional in the form, so when there is no address the endpoint answers `need_email` and the form asks for one rather than dead-ending.

**The gate is server-side.** `bhela_bm_process_submission()` refuses an unverified number. The disabled submit button is only a hint; a crafted POST still fails. The proof is keyed to the *normalised* number, so editing the phone after verifying invalidates it with no extra bookkeeping.

**Limits**, per number: 60-second resend cooldown, 5 sends/day, 5 wrong guesses (the fifth destroys the code). Plus a per-IP ceiling. These are cost control as much as abuse control — an open send endpoint is an SMS-bombing tool pointed at a stranger's phone, and every send is billable.

The code is stored only as `hash_hmac( 'sha256', $code, wp_salt( 'auth' ) )` and never appears in a response. Verified bookings carry `_bhela_phone_verified` (channel + time) and show a ✅ in the bookings list; bookings that predate this, or that the owner types in by hand, simply carry no stamp.

### Gateway note — BulkSMSBD

BulkSMSBD answers **HTTP 200 for failures too**, putting the real verdict in the body as `response_code` (202 = accepted; 1007 = no balance; 1002 = sender ID not approved; …). Judging by HTTP status alone reported success for messages that never left the building — which matters most here, because the guest waits for a code that is not coming and the email fallback never fires. `bhela_bm_send_sms()` now parses that field, and `bhela_bm_sms_gateway_error()` turns the code into something actionable. Gateways without the field are unaffected. The preset also sends the `type=text` parameter BulkSMSBD requires.

The gateway URL must be **HTTPS** — the SSRF guard rejects plaintext, so the `http://` URL in BulkSMSBD's docs will not work. `https://bulksmsbd.net/api/smsapi` does.

### SMS credit on the dashboard

`bhela_bm_sms_balance()` reads the gateway's balance API and shows it on the BHELA Dashboard and in Settings, with a manual refresh. Cached for 15 minutes — the dashboard is opened all day and this is an external HTTP call otherwise; failures are cached for 2 minutes so a dead gateway cannot stall every page load behind a timeout.

Below `sms_low_balance` (default ৳100) the card turns amber and says what will break. The warning is sharper when verification is on: once the credit hits zero, codes fall back to email, and **a guest who leaves the email blank cannot book at all**.

The card only appears for a provider that publishes a balance endpoint (currently BulkSMSBD); other gateways simply do not show it. The API key is never rendered into the page or stored in the cached payload.

## Trip Cost Sheet

`Accounts → 🧾 Cost Sheets`. One sheet per trip: the standing expense heads, each with 1st/2nd/3rd payment columns and a remark, plus spare rows for one-offs.

### Heads are the owner's, not the code's

`Settings → 🧾 Lists` — rename, reorder, add, retire. The 21 shipped heads are only defaults; `bhela_bm_cost_head_defaults()` seeds the `bhela_bm_cost_heads` option, and a "reset to defaults" drops the override. Same defaults-plus-override shape as the staff roles.

**Rows are stored against stable slugs, not positions.** That is what makes editing safe: renaming a head changes a label and nothing else, and the figures stay attached. Sheets written before v2.23.0 stored a positional array; `bhela_bm_cost_stored_lines()` maps index → slug on read and the next save rewrites it in the new shape, so there is no migration to run.

**Retire, don't delete.** A head still shows on the sheets that already used it — a closed month must never change — but disappears from new ones. The Settings table marks which heads carry history.

Spare rows are a minimum (5), not a cap: **+ Add row** adds more, up to 30. July 2026's 15–16 trip used fourteen one-off rows in a single sheet, which is why three was not enough and fifteen was too close.

**Earnings are not typed in.** The sheet reads the travel date's booking total through `bhela_bm_report_rows()`, so cost and revenue can never disagree with the Trip Report. The field stays editable for outside income; when it differs from the booking figure the sheet says so and offers a one-click reset.

**Approval chain** — each step stamps the user and time, and writes to the activity log:

```
draft ──submit──▶ prepared ──check──▶ checked ──approve──▶ approved (locked)
                     ▲                    │                     │
                     └────── return ──────┘                     │
                     ◀──────────── unlock (admin only) ─────────┘
```

An approved sheet is locked in the save handler, not just in the UI — a crafted POST cannot edit it either.

## Expenses & the Monthly Statement

`Accounts → 💸 Expenses` records what is spent outside a trip — advertising, renovation, one-off purchases — with date, type, amount, payment method, payment date, means of verification and remark. It reproduces the "Digital Marketing & Renovation Report" kept by hand. Filter by month and type; the filter bar shows the month's total.

`Accounts → 📈 Monthly Statement` puts the two together: the month's **approved** cost sheets as trip rows, then one deduction row per expense type, then gross profit — plus cost and profit per person, and the sign-offs carried through from the sheets.

Types and payment methods are editable in `Settings → 🧾 Lists`, same defaults-plus-override shape as the cost heads. Because deductions are grouped by whatever types exist, **adding a type grows the statement with no code change**.

**Only approved sheets count.** A sheet still being typed would otherwise move the month's profit on every save. Unapproved ones are listed with a warning and linked, rather than silently omitted.

### Verified against a real month

July 2026, rebuilt from the owner's PDFs, reproduces exactly: 13 trips, 335 guests, ৳1,922,500 revenue, ৳1,087,597 trip cost, ৳834,903 trip profit, ৳336,689 deductions, **৳498,214 gross profit**, ৳4,251.60 cost per person, ৳1,487.21 profit per person.

Two modelling notes that came out of that:

- Cost per person on the printed statement is computed on trip cost **plus** deductions, not trip cost alone — the two readings differ by about ৳1,000 a head. The screen follows the printed sheet and says so.
- The statement deducts the renovation **adjustment** (৳250,000), not the raw renovation spend also listed in the marketing report (৳79,460 in July). Recording both would double-count.

## Staff salary

`Setup → ⚙️ Settings → 👷 Staff` holds the roster: name, designation, employment type (trip based / monthly / both), per-trip rate, monthly salary, account. `Accounts → 👷 Salary` is one sheet per month.

**Trips completed defaults to the number of approved cost sheets in that month**, and is editable per person for anyone who missed one. Sub-total is rate × trips; payable adds any monthly salary; advance is subtracted to give payment-after-advance. Settlement, adjustment and means of verification are typed, following the printed sheet.

**Roster details are snapshotted onto the sheet when it is saved.** A pay rise next month must not rewrite what was paid last month — a saved sheet keeps the rate, name and designation it was saved with, while a new sheet picks up the current roster. Marking someone as left keeps them on the months they were paid for and drops them from new ones.

Verified against July 2026: 13 trips, ten staff, ৳265,500 in trip pay, ৳285,500 payable including the manager's ৳20,000 monthly, ৳265,500 after his ৳20,000 advance — and the supervisor correctly at 8 trips rather than 13.

Payroll is off for Booking Staff by default: pay rates are visible on this screen.

## Team & roles

`Setup → 👥 Team` (administrators only) lists who has access and carries the **permission matrix** — a checkbox per permission per role, saved from the page.

Creating users and assigning roles stays in **Users → Add New** — these are ordinary WordPress roles, so they appear in its dropdown with no extra code. This screen decides what those roles *may do*.

Shipped defaults:

| Role | Bookings | Dashboard & Trip Report | Trip Calendar | Cost sheets | Settings |
|---|---|---|---|---|---|
| **BHELA Manager** | full | ✓ | ✓ | prepare + check | — |
| **BHELA Booking Staff** | create & edit | ✓ | — | — | — |
| **BHELA Cost Checker** | — | — | — | all sheets, check | — |
| **BHELA Cost Preparer** | — | — | — | own sheets only | — |
| Administrator | full | ✓ | ✓ | **approve** / unlock | ✓ |

### How the matrix works

`bhela_bm_permissions()` is the registry: one checkbox → a bundle of capabilities. It doubles as the allow-list, which is why `manage_options`, `edit_posts`, `activate_plugins` and `list_users` are absent — they cannot be granted from the UI at all.

Some permissions carry a `requires`. `reports` needs `bookings_edit` because the Dashboard and Trip Report are submenus of Bookings; granting the capability alone would register menu items the user could never reach. `bhela_bm_normalise_perms()` pulls in missing prerequisites and drops unknown keys — on save **and** on read, so a stored set can never describe a half-granted permission however it got there. The browser cascades the same rules for feedback; the server never trusts it.

Granting both **Check** and **Approve** to one role lets that person approve their own sheet. That is allowed, with a warning on the page.

### Defaults vs. the owner's choices

- `bhela_bm_role_defaults()` — the shipped baseline, described by permission key.
- Option **`bhela_bm_role_perms`** — only roles the owner actually changed.
- `bhela_bm_roles()` composes capabilities from `override ?? default`, so the sync, the matrix and `bhela_bm_owned_caps()` all read one source and neither know nor care whether anything was customised.

A role that was never touched keeps following code defaults, so later releases can still adjust it. A customised role shows *(customised)* with a **reset to defaults** link.

This layering is what makes the authoritative sync safe: it enforces a definition that already contains the owner's choices, so a version bump cannot quietly undo them.

Both CPTs declare their own `capability_type` (`bhela_booking` / `bhela_cost`) rather than `'post'`. That is the load-bearing decision: with `'post'`, booking staff would need `edit_posts` and would inherit the site's pages, posts and every other plugin's content. None of these roles hold `edit_posts`, `manage_options`, `activate_plugins` or `list_users`.

Screens are gated on plugin capabilities, not WordPress ones — `bhela_view_reports` for the Dashboard and Trip Report, `bhela_manage_trips` for the Trip Calendar, `bhela_cost_*` for the sheet workflow.

**Role sync is authoritative.** `bhela_bm_install_roles()` runs on activation and whenever `BHELA_BM_ROLES_VERSION` moves. It adds missing capabilities *and removes plugin capabilities the current definition no longer grants* — add-only syncing looks safer but silently leaves old permissions in place when a role is narrowed. Capabilities from outside this plugin are never touched.

Upgrade note: bookings previously ran on generic `post` capabilities, so any role with `edit_others_posts` (Editor by default) could manage them. The sync carries that across rather than revoking it.

## Settings (`bhela_bm_settings` option)

Business info · payment details (bKash/Nagad/bank/QR) · advance % · invoice prefix/note · weekend days · holidays · cabin rates (`bhela_bm_rates`) · trip calendar (`bhela_bm_trips`) · email notification toggles · SMS gateway config. Managed under **Setup → ⚙️ Settings**.

## Security

- Every AJAX handler uses `check_ajax_referer`; every admin action requires capability + nonce.
- Booking CPT is private — `public=false`, `publicly_queryable=false`, not in REST. Booking meta not REST-exposed.
- Invoice links: full `wp_hash` secret + timing-safe `hash_equals` + `edit_post` fallback.
- SMS API key stored masked in the UI, never echoed in full, never logged.
- Public submit: honeypot + per-IP rate limit. Tracker: failed-lookup rate limit only.
- All include files carry an `ABSPATH` guard.

## Provisioning

The `bhela` theme auto-creates the booking pages, trip calendar, and menu on activation — and once per released version via a capability-gated `admin_init` check (skips AJAX/cron). Nothing to run manually; configure under **Setup → ⚙️ Settings**.

## Changelog (recent)

- **2.25.1** — The booking form now survives a full-page cache. The nonce is no
  longer printed into the HTML (it expires in 12–24h, so a cached page broke every
  AJAX call); the browser fetches a fresh one and retries once. Rate limits read the
  real client IP behind a CDN or CGNAT via `BHELA_TRUSTED_PROXIES`. Approved cost
  sheets whose bookings changed after sign-off are flagged on both reports. A salary
  sheet warns when no cost sheets are approved yet, instead of silently showing ৳0.
  See `docs/CACHING.md`.

- **2.25.0** — Fixes the ৳ sign, which Segoe UI carries but draws a third too narrow; a
  `unicode-range` face now sources it from Nirmala UI on every admin screen, the printed cost
  sheet, the invoice and the emails. Undated cost sheets are surfaced on both reports, flagged
  in the list and refused approval — they belong to no month, so an approved one would be filed
  into nowhere. Test harnesses are isolated from real site data.

- **2.24.0** — Yearly Report: twelve monthly statements side by side, season totals, margin,
  profit-per-month chart and the year's expense mix. Financial year (Jul–Jun) or calendar.
  Fixes `bhela_bm_money()` printing a loss as `৳-215,200` — the sign now precedes the symbol,
  on every screen that can show one. Fixes the filter bar collapsing on Trip Report, Monthly
  Statement, Salary and Yearly (a CSS class-name collision), now guarded by a test.

- **2.23.0** — Admin design system (`assets/admin.css`): one screen header, one status pill, one
  ledger treatment across all sixteen screens; 12 inline `<style>` blocks and 58 hand-typed hex
  values removed. Editable trip cost heads, Expenses, Monthly Statement and Staff Salary.
  Security: staff roles no longer receive `upload_files`; the invoice link now sends
  `no-store` and `X-Robots-Tag: noindex`.
- **2.6.2** — Email Notifications settings (toggles, recipient, From/Reply-To, test send).
- **2.6.1** — Fix double-encoded WhatsApp prefill URL.
- **2.6.0** — SMS notifications (provider-agnostic; 3 triggers).
- **2.5.0** — Per-date cabin inventory + per-person pricing in form and invoice.
- **2.4.x** — Booking-form UX redesign; security hardening (tracker throttle, invoice key).
