# BHELA Booking Engine

Complete booking engine for **BHELA – The Haor Exclusive** houseboat. Cabin pricing, per-date cabin inventory, booking statuses, secure invoices, and email + SMS notifications.

- **Version:** 2.21.0
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
- `bhela_bm_roles()` — staff roles with their capabilities; the single source of truth the rest of the plugin reads.
- `bhela_bm_permissions()` — the togglable permission registry, and the allow-list for what the Team screen may grant.
- `bhela_bm_normalise_perms( $perms )` — drops unknown keys, pulls in prerequisites.
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

`Bookings → 👥 Team` (administrators only) lists who has access and carries the **permission matrix** — a checkbox per permission per role, saved from the page.

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
