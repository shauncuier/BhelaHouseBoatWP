# BHELA Booking Engine

Complete booking engine for **BHELA – The Haor Exclusive** houseboat. Cabin pricing, per-date cabin inventory, booking statuses, secure invoices, and email + SMS notifications.

- **Version:** 2.6.2
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
| `templates/invoice.php` | Printable invoice |

## Key functions / extension points

- `bhela_bm_send_sms( $number, $message )` — send via the configured gateway.
- `bhela_bm_render_sms( $template, $booking_id )` — fill `{placeholders}` from a booking.
- `bhela_bm_trip_availability( $date )` → `total / booked / available / status`.
- `bhela_bm_calc_multi( $cabins, $date )` — authoritative per-cabin pricing.
- Notifications fire from `bhela_bm_process_submission()` (new booking) and `bhela_bm_save_booking()` (status change).

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
