# BHELA Booking Engine

Complete booking engine for **BHELA – The Haor Exclusive** houseboat. Cabin pricing, per-date cabin inventory, booking statuses, secure invoices, and email + SMS notifications.

- **Version:** 2.20.0
- **Requires:** WordPress 6.0+, PHP 8.0+
- **Pairs with:** the `bhela` theme (Midnight Monsoon). Works standalone; the theme adds the booking pages.

> **Owner-facing guide:** `wp-content/docs/BHELA-Owner-Manual.md` (styled version published as an Artifact).
> **Design/feature history:** `wp-content/docs/plans/`.

## Features

- **Booking form** — shortcode `[bhela_booking_form]`: stepper wizard, live per-person pricing, auto cabin-plan, honeypot + per-IP throttle.
- **Trip calendar** — shortcode `[bhela_trip_calendar]`: admin-managed dates + per-date cabin inventory (X/6 available).
- **Reviews** — shortcode `[bhela_reviews]`.
- **Cabin inventory** — `booked` count per trip; frontend + form respect remaining cabins; server rejects over-capacity submits.
- **Invoices** — secure per-booking link (`wp_hash` + `hash_equals`), per-cabin per-person line items, bKash/Nagad/bank/QR.
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
| `includes/trips.php` | Trip calendar admin + shortcode + availability helper |
| `includes/reviews.php` | Reviews CPT |
| `includes/admin.php` | Bookings columns, edit meta box, Settings page, dashboard widget |
| `includes/reports.php` | Trip Report screen — bookings for a date/range with advance, due and totals; print, WhatsApp text and CSV export |
| `includes/costs.php` | Trip Cost Sheet CPT + prepare/check/approve workflow and the printable sheet |
| `includes/roles.php` | Staff roles, every plugin capability, and the read-only Team reference screen |
| `templates/invoice.php` | Printable invoice |

## Key functions / extension points

- `bhela_bm_send_sms( $number, $message )` — send via the configured gateway.
- `bhela_bm_render_sms( $template, $booking_id )` — fill `{placeholders}` from a booking.
- `bhela_bm_trip_availability( $date )` → `total / booked / available / status`.
- `bhela_bm_report_rows( $from, $to, $with_cancelled )` → `rows / totals` for a travel-date range.
- `bhela_bm_cost_items()` — the fixed expense heads on the trip cost sheet.
- `bhela_bm_cost_transitions()` — the workflow state machine (from-state, target, required capability).
- `bhela_bm_roles()` — staff role definitions; the single source of truth for permissions.
- `bhela_bm_calc_multi( $cabins, $date )` — authoritative per-cabin pricing.
- Notifications fire from `bhela_bm_process_submission()` (new booking) and `bhela_bm_save_booking()` (status change).

## Trip Cost Sheet

`Bookings → 🧾 Cost Sheets`. One sheet per trip: the 21 expense heads from the operations spreadsheet plus 3 free-text rows, each with 1st/2nd/3rd payment columns and a remark.

**Earnings are not typed in.** The sheet reads the travel date's booking total through `bhela_bm_report_rows()`, so cost and revenue can never disagree with the Trip Report. The field stays editable for outside income; when it differs from the booking figure the sheet says so and offers a one-click reset.

**Approval chain** — each step stamps the user and time, and writes to the activity log:

```
draft ──submit──▶ prepared ──check──▶ checked ──approve──▶ approved (locked)
                     ▲                    │                     │
                     └────── return ──────┘                     │
                     ◀──────────── unlock (admin only) ─────────┘
```

An approved sheet is locked in the save handler, not just in the UI — a crafted POST cannot edit it either.

## Team & roles

`Bookings → 👥 Team` (administrators only) is **read-only**: who currently has access, and a generated table of what each role may actually do.

Creating users and assigning roles stays in **Users → Add New** — these are ordinary WordPress roles, so they appear in its dropdown with no extra code. Duplicating that here would mean two places to change a role and two places to keep correct. What WordPress cannot show is what a role *permits*: its user list prints role names and stops, so nothing there reveals that a Cost Checker cannot approve. That table is the screen's reason to exist, and it is built from `bhela_bm_roles()` so it cannot drift from what the site enforces.

| Role | Bookings | Dashboard & Trip Report | Trip Calendar | Cost sheets | Settings |
|---|---|---|---|---|---|
| **BHELA Manager** | full | ✓ | ✓ | prepare + check | — |
| **BHELA Booking Staff** | create & edit | ✓ | — | — | — |
| **BHELA Cost Checker** | — | — | — | all sheets, check | — |
| **BHELA Cost Preparer** | — | — | — | own sheets only | — |
| Administrator | full | ✓ | ✓ | **approve** / unlock | ✓ |

Both CPTs declare their own `capability_type` (`bhela_booking` / `bhela_cost`) rather than `'post'`. That is the load-bearing decision: with `'post'`, booking staff would need `edit_posts` and would inherit the site's pages, posts and every other plugin's content. None of these roles hold `edit_posts`, `manage_options`, `activate_plugins` or `list_users`.

Screens are gated on plugin capabilities, not WordPress ones — `bhela_view_reports` for the Dashboard and Trip Report, `bhela_manage_trips` for the Trip Calendar, `bhela_cost_*` for the sheet workflow.

**Role sync is authoritative.** `bhela_bm_install_roles()` runs on activation and whenever `BHELA_BM_ROLES_VERSION` moves. It adds missing capabilities *and removes plugin capabilities the current definition no longer grants* — add-only syncing looks safer but silently leaves old permissions in place when a role is narrowed. Capabilities from outside this plugin are never touched.

Upgrade note: bookings previously ran on generic `post` capabilities, so any role with `edit_others_posts` (Editor by default) could manage them. The sync carries that across rather than revoking it.

## Settings (`bhela_bm_settings` option)

Business info · payment details (bKash/Nagad/bank/QR) · advance % · invoice prefix/note · weekend days · holidays · cabin rates (`bhela_bm_rates`) · trip calendar (`bhela_bm_trips`) · email notification toggles · SMS gateway config. Managed under **Bookings → Settings**.

## Security

- Every AJAX handler uses `check_ajax_referer`; every admin action requires capability + nonce.
- Booking CPT is private — `public=false`, `publicly_queryable=false`, not in REST. Booking meta not REST-exposed.
- Invoice links: full `wp_hash` secret + timing-safe `hash_equals` + `edit_post` fallback.
- SMS API key stored masked in the UI, never echoed in full, never logged.
- Public submit: honeypot + per-IP rate limit. Tracker: failed-lookup rate limit only.
- All include files carry an `ABSPATH` guard.

## Provisioning

The `bhela` theme auto-creates the booking pages, trip calendar, and menu on activation — and once per released version via a capability-gated `admin_init` check (skips AJAX/cron). Nothing to run manually; configure under **Bookings → Settings**.

## Changelog (recent)

- **2.6.2** — Email Notifications settings (toggles, recipient, From/Reply-To, test send).
- **2.6.1** — Fix double-encoded WhatsApp prefill URL.
- **2.6.0** — SMS notifications (provider-agnostic; 3 triggers).
- **2.5.0** — Per-date cabin inventory + per-person pricing in form and invoice.
- **2.4.x** — Booking-form UX redesign; security hardening (tracker throttle, invoice key).
