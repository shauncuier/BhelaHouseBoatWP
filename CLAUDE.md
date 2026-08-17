# BHELA — Project Intelligence (CLAUDE.md)

> **Purpose:** This is the canonical context document for AI assistants (Claude Code, Gemini, etc.) working on the BHELA WordPress project.
> Commit this file to GitHub so it's available on any machine you clone to.
>
> Last updated: 2026-07-22 · Theme & Plugin v2.14.0 (single shared version)

---

## 1. Project Overview

**BHELA – The Haor Exclusive** is a premium houseboat tourism website built on WordPress, serving customers exploring Tanguar Haor, Sunamganj, Bangladesh.

| Component | Path | Current Version | Purpose |
|---|---|---|---|
| **BHELA Theme** | `themes/bhela/` | v2.11.2 | Full custom theme — "Midnight Monsoon" dark-teal luxury design |
| **BHELA Booking Engine** | `plugins/bhela-booking/` | v2.7.1 | Booking form, pricing engine, invoices, emails, SMS, trip calendar, reviews |

**GitHub:** https://github.com/shauncuier/BhelaHouseBoatWP
**Branch:** `main`
**Author/Dev:** 3s-Soft (https://3s-soft.com)
**Local dev:** LocalWP (Local by Flywheel) — current site name `bhela`
**Local URL:** http://bhela.local/ · admin at http://bhela.local/wp-admin/

> The site name is **per-machine and it has changed before** — an earlier `bhela-house-boat`
> site is still registered in LocalWP, so a hardcoded path can quietly point at a
> different database. Never hardcode the repo path: resolve it with
> `git rev-parse --show-toplevel`. See §5.1.

---

## 2. Repository Structure

```
wp-content/                          ← Git root
├── CLAUDE.md                        ← THIS FILE — AI project intelligence
├── README.md                        ← Project landing page
├── BHELA-SETUP-README.md            ← Activation & setup guide
├── .gitignore                       ← Whitelist-based (only tracks custom code)
│
├── themes/bhela/                    ← Custom theme (Midnight Monsoon)
│   ├── style.css                    ← Master stylesheet + WP theme header (Version here!)
│   ├── functions.php                ← Theme setup, enqueues, customizer, auto-page creation
│   ├── front-page.php               ← Homepage (hero, estimator, sections)
│   ├── header.php / footer.php      ← Site-wide header & footer
│   ├── index.php                    ← Blog archive (হাওর জার্নাল)
│   ├── single.php                   ← Single blog post
│   ├── page.php                     ← Default page template
│   ├── 404.php                      ← 404 page
│   ├── theme.json                   ← Block editor settings & color palette
│   ├── inc/
│   │   └── block-patterns.php       ← Gutenberg block patterns
│   ├── page-templates/
│   │   ├── template-booking.php     ← বুক করুন (Book Now)
│   │   ├── template-cabins.php      ← কেবিন ও রেট (Cabins & Rates)
│   │   ├── template-schedule.php    ← ট্রিপ সিডিউল (Trip Schedule)
│   │   ├── template-food.php        ← খাবার মেনু (Food Menu)
│   │   ├── template-gallery.php     ← গ্যালারি (Gallery)
│   │   ├── template-faq.php         ← সাধারণ প্রশ্ন (FAQ)
│   │   ├── template-guide.php       ← বুকিং গাইড (Booking Guide)
│   │   ├── template-policy.php      ← বুকিং নীতিমালা (Policies)
│   │   └── template-fullwidth.php   ← Full-width Elementor template
│   ├── assets/
│   │   ├── css/                     ← Additional stylesheets
│   │   ├── js/theme.js              ← Frontend JS (hero estimator, etc.)
│   │   └── images/{hero,cabins,spots,food,boat}/  ← Auto-gallery source dirs
│   └── screenshot.png               ← WP theme screenshot
│
├── plugins/bhela-booking/           ← Custom booking plugin
│   ├── bhela-booking.php            ← Bootstrap, settings, pricing engine, CPT
│   ├── includes/
│   │   ├── frontend.php             ← Booking form, AJAX handlers, submission processor
│   │   ├── invoice.php              ← Secure invoice link generation
│   │   ├── emails.php               ← Admin + customer email notifications
│   │   ├── sms.php                  ← Provider-agnostic SMS (BulkSMSBD preset + custom)
│   │   ├── otp.php                  ← Mobile verification on the booking form (SMS, email fallback)
│   │   ├── trips.php                ← Trip calendar admin + shortcode + availability
│   │   ├── reviews.php              ← Reviews CPT + admin + shortcode
│   │   ├── admin.php                ← Admin UI: columns, meta boxes, settings page, dashboard widget
│   │   ├── reports.php              ← Trip Report: per-date bookings, advance/due, print + WhatsApp + CSV
│   │   ├── costs.php                ← Trip Cost Sheet CPT + prepare/check/approve workflow
│   │   ├── expenses.php             ← Expense CPT + editable types/methods (marketing, renovation)
│   │   ├── statement.php            ← Monthly Statement: approved trips − month's expenses
│   │   ├── yearly.php               ← Yearly Report: 12 monthly statements + season totals
│   │   ├── salary.php               ← Staff roster + monthly salary sheet
│   │   ├── roles.php                ← Staff roles, plugin capabilities, Team reference screen
│   │   ├── audit.php                ← Append-only audit trail: the one DB table, one writer, no clear button
│   │   ├── inventory-core.php       ← Stock post types + the lock. Loads on EVERY request (see §3.8)
│   │   ├── inventory.php            ← Stock lists, quantity model, monthly carry-forward, close workflow, screens
│   │   ├── inventory-import.php     ← Column-mapped CSV importer: upload → map → dry run → commit
│   │   ├── ui.php                   ← Shared admin UI: screen header, status pill, tone map
│   │   └── guide.php                ← Embedded admin guide
│   ├── assets/
│   │   ├── admin.css                ← wp-admin design system (tokens, header, ledger, pills)
│   │   ├── booking.css              ← Booking form styles (29KB)
│   │   └── booking.js               ← Booking form logic + stepper wizard (41KB)
│   └── templates/
│       └── invoice.php              ← Printable invoice template
│
├── tests/                           ← Regression suites (outside themes/ & plugins/, never shipped)
│   ├── README.md                    ← How to run and how to add a harness
│   ├── run.php                      ← CLI runner — loads the PHP extensions each harness needs
│   ├── bootstrap.php                ← Boots WP, resolves the LocalWP DB port, provides ok()
│   ├── sweep.php                    ← Clears ZZ* fixtures left by a crashed run
│   ├── *-test.php                   ← 9 headless harnesses
│   └── bhela-tests.php              ← Older browser suite (open as an admin)
│
├── docs/
│   ├── BHELA-Owner-Manual.md        ← Non-technical owner guide (Bangla-friendly)
│   └── plans/                       ← Feature implementation plans (historical)
│       ├── 2026-07-14-blog.md
│       ├── 2026-07-14-cabin-inventory.md
│       └── 2026-07-14-sms.md
│
├── .agents/                         ← Gemini / Antigravity IDE skills & rules
│   ├── skills/bhela-release/SKILL.md  ← Automated release workflow
│   ├── rules/graphify.md            ← Knowledge graph rules
│   └── workflows/graphify.md        ← Graphify workflow
│
├── .claude/                         ← Claude Code
│   ├── skills/bhela-test/           ← Run the regression suite (committed, syncs across machines)
│   ├── settings.json                ← Version-sync hook (committed, team-wide)
│   └── settings.local.json          ← Permission grants (git-ignored)
│
├── graphify-out/                    ← Knowledge graph output (auto-generated)
│
├── bhela-theme-v*.zip               ← Release ZIP (theme)
└── bhela-booking-v*.zip             ← Release ZIP (plugin)
```

---

## 3. Architecture & Key Concepts

### 3.1 Monorepo Layout

This is a **wp-content-level monorepo**. Only two directories are tracked:
- `themes/bhela/` — the custom theme
- `plugins/bhela-booking/` — the custom booking plugin

Everything else (core WP, other plugins, uploads) is git-ignored via a **whitelist `.gitignore`**.

### 3.2 Theme ↔ Plugin Communication

The theme and plugin are **tightly coupled** but the plugin can work standalone:

- **Settings source of truth:** `bhela_bm_settings` option (managed by plugin)
- **Customizer fallback:** Theme customizer fields (phone, WhatsApp, etc.) fall back to plugin settings if not set
- **Rate injection:** Theme's `functions.php` uses `wp_localize_script()` to inject cabin rates, holidays, and weekend days from the DB into `theme.js` for the live hero estimator
- **Shortcodes from plugin:**
  - `[bhela_booking_form]` — booking wizard
  - `[bhela_trip_calendar]` — trip schedule
  - `[bhela_reviews]` — guest reviews
- **Auto-provisioning:** On theme activation, `functions.php` auto-creates all 7 Bengali-titled pages with correct page templates, plus a primary nav menu

### 3.3 Pricing Engine

Location: `bhela-booking.php` → `bhela_bm_calc_multi()`

- **Regular/Holiday rate:** Applied on weekend days (configurable, default Fri+Sat) and holiday dates
- **Weekday rate:** 20% discount on non-weekend, non-holiday days
- **A cabin is opened for adults only:** every cabin needs at least 2 adults, so children never justify an extra cabin (2 cabins require 4 adults). Cabin tier is the adult count in that cabin. 4–8 children ride along in that cabin and never push the booking into a larger (cheaper-per-head) tier.
- **Children pricing:** 0–4 free (share food + bed with parents, excluded from cabin size), 4–8 pay a **flat fee** (`child_fee` setting, default ৳5,000) with **no weekday discount**, 9+ full rate
  - Example (weekend): 4 adults + one 5-year-old = 4-person cabin → 4 × ৳10,000 + ৳5,000 = **৳45,000** (not a 5-person cabin at ৳9,000/head)
- **Per-cabin, per-person** calculation with multi-cabin support

### 3.4 Booking Flow

1. Customer visits Book Now page → stepper wizard form
2. Live pricing calculated client-side (`booking.js`)
3. AJAX submit → server validates (nonce, honeypot, IP throttle, cabin availability)
4. Creates private CPT post (`bhela_booking`) with all meta
5. Returns booking number + WhatsApp deep-link + invoice URL
6. Admin notified via email (+ optional SMS)
7. Admin manages status: Pending → Advance Paid → Confirmed → Completed / Cancelled
8. Status change to "Confirmed" auto-emails customer

### 3.5 Security Model

- All AJAX: `check_ajax_referer()` + nonce verification
- Booking CPT: `public=false`, `publicly_queryable=false`, no REST exposure
- Invoice links: `wp_hash()` secret + `hash_equals()` (timing-safe)
- SMS API keys: stored masked, never echoed/logged
- Form submit: honeypot field + per-IP rate limiting
- All include files: `ABSPATH` guard

### 3.6 Database

| Option Key | Contents |
|---|---|
| `bhela_bm_settings` | All business settings (phones, payment details, advance %, invoice prefix, weekend days, holidays, email/SMS config) |
| `bhela_bm_rates` | Cabin rates array (regular + weekday per cabin) |
| `bhela_bm_trips` | Trip calendar entries |
| `bhela_bm_role_perms` | Per-role permission overrides set from the Team screen (only customised roles) |
| `bhela_bm_cost_heads` | Owner-edited trip cost heads (slug => label, retired) |
| `bhela_bm_expense_types` / `bhela_bm_expense_methods` | Owner-edited expense lists |
| `bhela_bm_staff` | Staff roster (id => name, designation, type, rate, monthly, account) |
| `bhela_bm_inv_categories` / `bhela_bm_inv_subcats` / `bhela_bm_inv_locations` | Owner-edited stock lists. A category's **code** is frozen — every Item ID contains it |
| `bhela_bm_inv_periods` | `YYYY-MM` => period post ID. **This option is the uniqueness constraint** for one-sheet-per-month |
| `bhela_bm_inv_seq` | Per-category Item ID counter (`KIT` => 42). Numbers are never reused |
| `bhela_bm_audit_db` | Audit-table schema version, compared on `admin_init` priority 5 |

The plugin owns exactly **one database table**, `{prefix}bhela_bm_audit` — see §3.7.

### 3.7 The audit trail is not the activity log

Two stores, deliberately different:

| | `includes/log.php` | `includes/audit.php` |
|---|---|---|
| Answers | "did that email go out?" | "who changed this figure, from what, and why?" |
| Storage | one `wp_options` row, 300-entry ring buffer | `{prefix}bhela_bm_audit`, a real table |
| Shape | a message string | `old_value` / `new_value` / `reason` / `approval_ref` columns |
| Retention | capped, and **clearable in one click** | never pruned, and there is **no clear button** |

Both of the log's affordances are correct for diagnostics and disqualifying for audit, which is why the register did not extend it. There is exactly **one** SQL writer (`bhela_bm_audit()`), it only ever `INSERT`s, and there is no `uninstall.php` and no `register_uninstall_hook` — `tests/inventory-test.php` asserts all of that at source level. Do not add a delete path.

Bookings are stored as a **private Custom Post Type** (`bhela_booking`) with post meta for each field.

---

## 4. Design System — "Midnight Monsoon"

| Token | Value | Usage |
|---|---|---|
| **Primary** | Deep ink-teal (`#0a1628` family) | Backgrounds, nav, footer |
| **Accent** | Mustard gold | CTAs, highlights, badges |
| **Secondary** | Warm sand-beige / cream | Contrast sections, cards |
| **Typography (BN)** | Hind Siliguri (sans-serif) | Bengali body text |
| **Typography (EN)** | Fraunces (serif) | English display headings |
| **Animations** | CSS transitions | Hover effects, modal fades, accordion |
| **Glassmorphism** | Backdrop-filter blur | Navigation bar |

### Elementor Compatibility

- Any page built with Elementor automatically takes over full layout (theme sections hidden)
- `template-fullwidth.php` provides edge-to-edge Elementor support
- Elementor Canvas template also available
- Theme Builder locations registered for header/footer override

---

## 5. Development Environment

### 5.1 Local Setup (LocalWP)

```
Site name:    bhela                        ← per-machine, do not depend on it
Local URL:    http://bhela.local/
WP root:      <LocalWP site>\app\public\
wp-content:   <LocalWP site>\app\public\wp-content\   ← Git root
```

On the current machine that resolves to `C:\Users\jashe\Local Sites\bhela\app\public\wp-content`,
but **do not write that path into a script.** A stale `bhela-house-boat` path survived a site
rename in this file and in the release skill, and both still exist in LocalWP — so the wrong one
answers, on a different database, with no error. Resolve the root instead:

```powershell
$root = (git rev-parse --show-toplevel).Replace('/', '\')   # git answers with forward slashes
```

Two BHELA sites are registered locally. `tests/bootstrap.php` already handles this correctly:
it matches the LocalWP site whose recorded path **contains this checkout** rather than trusting a
name, which is why the harnesses reach the right database on either machine.

### 5.2 Setting Up on a New Computer

1. **Install LocalWP** (https://localwp.com)
2. Create a new WordPress site (any name)
3. Clone this repo into the site's `wp-content/` directory:
   ```bash
   cd /path/to/local-site/app/public/
   rm -rf wp-content
   git clone https://github.com/shauncuier/BhelaHouseBoatWP.git wp-content
   ```
4. In wp-admin:
   - Activate **BHELA Booking Engine** plugin FIRST
   - Activate **BHELA** theme (auto-creates pages + menu)
   - Settings → Reading → set homepage
5. Import database if needed (or configure fresh via Bookings → Settings)

### 5.3 Required Tools

| Tool | Purpose | Install |
|---|---|---|
| **Git** | Version control | `winget install Git.Git` |
| **GitHub CLI** (`gh`) | Releases, PR management | `winget install GitHub.cli` |
| **LocalWP** | Local WordPress dev | https://localwp.com |
| **Node.js** (optional) | graphify knowledge graph | `winget install OpenJS.NodeJS` |

---

## 6. Coding Conventions

### 6.1 PHP

- **WordPress coding standards** — tabs for indentation, Yoda conditions where appropriate
- **Prefix everything** with `bhela_` (theme) or `bhela_bm_` (plugin) to avoid collisions
- All plugin includes have `if ( ! defined( 'ABSPATH' ) ) exit;` guard
- Settings accessed via `get_option('bhela_bm_settings')` with defaults from `bhela_bm_default_settings()`
- Use `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` for output escaping

### 6.2 JavaScript

- **Vanilla JS** — no jQuery dependency in frontend
- `booking.js` is a single-file stepper wizard with live pricing
- Theme JS uses data injected via `wp_localize_script()`

### 6.3 CSS

- **Vanilla CSS** — no preprocessors, no Tailwind
- Theme styles in `style.css` (44KB) — single-file approach
- Plugin front-end styles in `assets/booking.css` (29KB)
- Plugin **admin** styles in `assets/admin.css` — the design system for all sixteen wp-admin
  screens. Scoped to `.bhela-admin` (set on `<body>` by `bhela_bm_admin_body_class()`) and
  enqueued only where `bhela_bm_is_plugin_screen()` is true. **Never add an inline `<style>`
  block to an admin template** — that is exactly the drift this file replaced.
- CSS custom properties used for theming tokens

### 6.4 Bangla / i18n

- Page titles, labels, and UI text are primarily in **Bengali (বাংলা)**
- Text domain: `bhela` (theme), `bhela-booking` (plugin)
- No `.pot`/`.po` files currently — strings are hardcoded in Bengali

---

## 7. Version Management

### Version File Locations — **all six move together**

| File | Line | What to update |
|---|---|---|
| `themes/bhela/style.css` | 7 | `Version: X.Y.Z` |
| `themes/bhela/README.md` | 1 | `# 🎨 BHELA WordPress Theme (vX.Y.Z)` |
| `themes/bhela/functions.php` | 12 | `define( 'BHELA_VERSION', 'X.Y.Z' );` — **theme asset cache-buster; forgetting this ships stale CSS/JS** |
| `plugins/bhela-booking/bhela-booking.php` | 5 | ` * Version: X.Y.Z` |
| `plugins/bhela-booking/bhela-booking.php` | 16 | `define( 'BHELA_BM_VERSION', 'X.Y.Z' );` |
| `plugins/bhela-booking/README.md` | 5 | `- **Version:** X.Y.Z` — added after it silently sat a release behind |

### Versioning Rules

- **Theme and Plugin share ONE version number.** A release bumps all six fields above to the same `X.Y.Z`, even if only one component changed. (History: they used to be versioned independently — that caused `BHELA_VERSION` to lag `style.css` and serve stale assets. Never again.)
- **Major** (X.0.0): breaking changes, full redesign
- **Minor** (X.Y.0): new features, templates, shortcodes
- **Patch** (X.Y.Z): bug fixes, style tweaks, copy changes

A `PostToolUse` hook in `.claude/settings.json` warns as soon as they diverge, and
`tests/version-test.py` fails the suite if they are still out of sync at release. Both run the
same checker.

> ⚠️ **CRITICAL:** All six version fields MUST always match. The two theme constants (`BHELA_VERSION` in `functions.php`, `BHELA_BM_VERSION` in the plugin) are asset cache-busters — a mismatch is invisible in the header but breaks browser caching.

---

## 8. Release Process

Use the `bhela-release` skill (`.agents/skills/bhela-release/SKILL.md`) for the full automated release. Summary:

1. **Pre-flight:** `git status` + `git log` — skip if nothing new
2. **Bump versions** in the files listed above
3. **Commit:** `release: vTHEME_VERSION theme / vPLUGIN_VERSION plugin`
4. **Tag:** `git tag -a "vTHEME_VERSION" -m "Release vTHEME_VERSION — <summary>"`
5. **Push:** `git push origin main --tags`
6. **Build ZIPs:** Use .NET `ZipFile` API (NOT `Compress-Archive` — it writes backslashes that break WP installs on Linux)
7. **GitHub Release:** `gh release create` + `gh release upload`

> ⚠️ **ZIP WARNING:** Never use PowerShell's `Compress-Archive`. It writes `bhela\style.css` with backslashes. PHP's `ZipArchive::extractTo()` on Linux treats this as a flat filename, causing "missing style.css" errors. Always use .NET `ZipFile` with forward-slash entry paths.

---

## 9. Key Functions & Extension Points

### Plugin (`bhela-booking`)

| Function | File | Purpose |
|---|---|---|
| `bhela_bm_default_settings()` | `bhela-booking.php` | Returns all default settings array |
| `bhela_bm_calc_multi($cabins, $date)` | `includes/frontend.php` | Authoritative multi-cabin pricing |
| `bhela_bm_balance($total,$paid)` | `bhela-booking.php` | Due + `settled` — the one reading every guest-facing surface shares |
| `bhela_bm_booking_day_type($id)` | `bhela-booking.php` | Day type derived from the travel date; the stored meta is only a fallback |
| `bhela_bm_full_boat_label()` | `bhela-booking.php` | The whole-boat `_bhela_cabin_type` string — one copy for admin and form |
| `bhela_bm_sanitize_weekend_days($raw)` | `bhela-booking.php` | `date('w')` numbers, whitelisted 0–6 (Sunday is 0, so the filter is explicit) |
| `bhela_bm_invoice_data($id)` | `includes/invoice.php` | Everything the printable template needs — split out so the invoice is renderable in a test |
| `bhela_bm_csv_cell($value)` | `bhela-booking.php` | Neutralises a cell a spreadsheet would execute. **Every free-text export cell goes through it**; never a figure |
| `bhela_bm_audit($args)` | `includes/audit.php` | The ONLY writer to the audit table, and it only inserts. See §3.7 |
| `bhela_bm_inv_line_check($line)` | `includes/inventory.php` | The quantity invariant: `good+rep+ur+dam === close`. Reports a mismatch, never rebalances it |
| `bhela_bm_inv_line_key($item,$loc)` | `includes/inventory.php` | The line key. Returns the item ID today; the one place to change if stock ever splits by location |
| `bhela_bm_inv_period_id($month,$create)` | `includes/inventory.php` | Resolves — or mints, behind an `add_option` mutex — the one record for a month |
| `bhela_bm_inv_take_opening($id,$force)` | `includes/inventory.php` | Snapshots the predecessor's closings. A snapshot, not a live read, so a closed month keeps its figures |
| `bhela_bm_inv_opening_drift($id)` | `includes/inventory.php` | Whether the month underneath moved since. **Reports, never corrects** |
| `bhela_bm_inv_can_close($id)` | `includes/inventory.php` | The six things standing between a checked month and a closed one |
| `bhela_bm_inv_apply_lines($id,$posted,$can_adjust)` | `includes/inventory.php` | The save rules, split from the request handler so they are testable |
| `bhela_bm_inv_is_locked($id)` | `includes/inventory-core.php` | Loaded on every request, not just wp-admin — see §3.8 |
| `bhela_bm_inv_meta_write($id,$k,$v)` | `includes/inventory.php` | The only legitimate way to write `_bhela_inv_*` on a locked sheet |
| `bhela_bm_inv_mint_code($cat)` | `includes/inventory.php` | Next Item ID for a category. Skips a number already in use rather than colliding |
| `bhela_bm_process_submission()` | `includes/frontend.php` | Processes new booking AJAX submit |
| `bhela_bm_trip_availability($date)` | `includes/trips.php` | Returns `total/booked/available/status` |
| `bhela_bm_send_sms($number, $msg)` | `includes/sms.php` | Send via configured gateway |
| `bhela_bm_otp_verified($phone)` | `includes/otp.php` | Has this number been proven recently? |
| `bhela_bm_otp_gsm_safe($text)` | `includes/otp.php` | Force text into GSM-7 so an OTP stays 1 SMS part |
| `bhela_bm_sms_balance($force)` | `includes/sms.php` | Gateway credit, cached 15 min; drives the dashboard card |
| `bhela_bm_render_sms($tpl, $id)` | `includes/sms.php` | Fill `{placeholders}` from booking |
| `bhela_bm_save_booking()` | `includes/admin.php` | Save booking meta + trigger notifications |
| `bhela_bm_report_rows($from,$to,$cancelled)` | `includes/reports.php` | Bookings + money totals for a travel-date range |
| `bhela_bm_cost_heads($retired)` | `includes/costs.php` | Expense heads in force (owner-editable, slug => label) |
| `bhela_bm_cost_stored_lines($id)` | `includes/costs.php` | Sheet rows keyed by slug; converts legacy positional data on read |
| `bhela_bm_expense_rows($from,$to)` | `includes/expenses.php` | Expenses in a range, totalled per type |
| `bhela_bm_statement_data($month)` | `includes/statement.php` | A month's approved trips, deductions and gross profit. Gross = trip profit − expenses − **payroll** |
| `bhela_bm_salary_month_total($month,$trips)` | `includes/salary.php` | The month's wage bill, from SAVED sheets only. Pass `$trips` — see §13.11 |
| `bhela_bm_salary_rows($id,$month)` | `includes/salary.php` | Payroll rows; trips default from approved sheets |
| `bhela_bm_cost_transitions()` | `includes/costs.php` | Cost-sheet workflow: from-state → target + required capability |
| `bhela_bm_screen_header()` | `includes/ui.php` | The teal banner every admin screen opens with (also emits `.wp-header-end`) |
| `bhela_bm_status_pill()` | `includes/ui.php` | One pill for every status vocabulary — five tones × solid/soft |
| `bhela_bm_status_tone()` | `includes/ui.php` | Booking status → tone + weight |
| `bhela_bm_is_plugin_screen()` | `bhela-booking.php` | True on a BHELA admin screen; gates `admin.css` and the body class |
| `bhela_bm_roles()` | `includes/roles.php` | Staff roles + capabilities — the single source the plugin reads |
| `bhela_bm_permissions()` | `includes/roles.php` | Togglable permissions; also the allow-list for the Team screen |
| `bhela_bm_role_defaults()` | `includes/roles.php` | Shipped baseline, overridden by the `bhela_bm_role_perms` option |
| `bhela_bm_install_roles()` | `includes/roles.php` | Authoritative role sync (adds AND removes plugin caps) |

### Theme (`bhela`)

| Function | File | Purpose |
|---|---|---|
| `bhela_setup()` | `functions.php` | Theme supports, menus, auto-page creation |
| `bhela_enqueue_*()` | `functions.php` | Script/style enqueues + rate localization |
| `bhela_customizer_*()` | `functions.php` | Customizer panels (contact, homepage, images) |

### Shortcodes

| Shortcode | Registered in | Output |
|---|---|---|
| `[bhela_booking_form]` | `includes/frontend.php` | Multi-step booking wizard |
| `[bhela_trip_calendar]` | `includes/trips.php` | Trip schedule with availability |
| `[bhela_reviews]` | `includes/reviews.php` | Guest reviews grid |

---

## 10. Common Tasks

### Add a new page template

1. Create `themes/bhela/page-templates/template-{name}.php`
2. Add WordPress template header comment: `/* Template Name: {Name} */`
3. Optionally add auto-creation in `functions.php` → `bhela_auto_create_pages()`

### Add a new admin screen

1. Open the page with `bhela_bm_screen_header( $icon, $title, $lead, $actions )` and wrap it in
   `<div class="wrap bha-page">`
2. Build from the existing classes: `.bha-bar` (filters), `.bha-cards` (summary figures),
   `.bha-panel` (a section), `.bha-num` (every money column), `.bha-callout--attention`
   (something needing action), `bhela_bm_status_pill()` (any status)
3. Run every figure through `bhela_bm_money()` — including the print view
4. Give the menu item an emoji **no other item uses**
5. If a genuinely new component is needed, name it `bha-` and add it to `assets/admin.css`

### Add a new setting to the booking plugin

1. Add default value in `bhela_bm_default_settings()` in `bhela-booking.php`
2. Add the admin UI field in `includes/admin.php` → settings page render function
3. Ensure `sanitize_callback` handles the new field

### Modify pricing logic

1. Edit `bhela_bm_calc_multi()` in `bhela-booking.php` (server-side, authoritative)
2. Mirror changes in `assets/booking.js` (client-side, live preview)
3. Both MUST produce identical results

### Add a new email notification

1. Add toggle in `includes/admin.php` settings
2. Add default in `bhela_bm_default_settings()`
3. Add send logic in `includes/emails.php`
4. Trigger from `bhela_bm_process_submission()` or `bhela_bm_save_booking()`

### Add a new SMS trigger

1. Add toggle + template in `includes/admin.php` settings
2. Add placeholder rendering in `bhela_bm_render_sms()` in `includes/sms.php`
3. Trigger via `bhela_bm_send_sms()` at the appropriate hook point

---

## 11. Testing & Verification

### Regression suite (run this first)

```bash
php tests/run.php
```

Fourteen headless harnesses: security, the July 2026 statement reproduced to the taka, salary,
cost heads, the cost-sheet save round trip, the booking save handler, the stock register, every
admin screen, WCAG contrast, the front end behind a page cache, OTP, the SMS gateway, the six
version fields, and the yearly rollup.
Exits non-zero on failure. Any PHP 8.x binary works — `run.php` loads the extensions each
harness needs, so never hand-build a `php -d extension=…` command. The site must be running.

`DIED EARLY` is not a test failure: it means a harness stopped before finishing, almost always
because the site is not up. A run with no final summary is a failure, never a pass.

`tests/bhela-tests.php` is the older browser suite — open it while logged in as an
administrator. It covers pricing and availability; the CLI suite covers everything else.

See `tests/README.md` to add a harness. Claude Code users: the `bhela-test` skill wraps all of this.

### Local Testing

- **Site check:** Visit http://bhela.local/
- **Booking test:** Submit a test booking → verify AJAX response + booking in admin
- **Invoice test:** Open invoice link → verify rendering + print layout
- **Email test:** Use "Send Test Email" button in Bookings → Settings
- **SMS test:** Use "Send Test SMS" button (requires gateway config)
- **JS syntax:** `node --check assets/booking.js`

### Pre-Release Checks

- [ ] `php tests/run.php` passes — all fourteen harnesses
- [ ] All version numbers bumped and in sync
- [ ] `git status` clean after version bump commit
- [ ] ZIP files built with forward-slash paths (verify with ZipFile inspection)
- [ ] Theme ZIP installs correctly in a fresh WordPress
- [ ] Plugin ZIP installs and activates without errors

---

## 12. Documentation Map

| Document | Path | Audience |
|---|---|---|
| **CLAUDE.md** (this file) | `wp-content/CLAUDE.md` | AI assistants / developers |
| **README.md** | `wp-content/README.md` | Developers (repo overview) |
| **BHELA-SETUP-README.md** | `wp-content/BHELA-SETUP-README.md` | Developers (setup guide) |
| **Owner Manual** | `wp-content/docs/BHELA-Owner-Manual.md` | Site owner (non-technical) |
| **Theme README** | `themes/bhela/README.md` | Developers (theme details) |
| **Plugin README** | `plugins/bhela-booking/README.md` | Developers (plugin details) |
| **Release Skill** | `.agents/skills/bhela-release/SKILL.md` | AI assistants (release workflow) |
| **Feature Plans** | `docs/plans/` | Historical design decisions |

---

## 13. Gotchas & Known Issues

1. **Caching:** exclude `admin-ajax.php` and `?bhela_invoice=` — see `docs/CACHING.md`. Behind a CDN, set `BHELA_TRUSTED_PROXIES` in wp-config.php or every visitor shares one rate-limit bucket.
2. **LocalWP emails:** Local sites don't send real mail. Use WP Mail SMTP or FluentSMTP plugin in production.
2. **Compress-Archive backslash bug:** Never use PowerShell's `Compress-Archive` for release ZIPs (see §8).
3. **Elementor override:** If Elementor is used on a page, the theme's coded sections for that page disappear entirely — by design.
4. **Homepage editor content:** Adding any Gutenberg blocks to the front page REPLACES the coded homepage design. Leave it empty to keep the designed homepage.
5. **Plugin-first activation:** Always activate the booking plugin BEFORE the theme, or auto-page creation may fail.
6. **settings.local.json is git-ignored:** The `.claude/` directory is excluded from git. Claude Code permissions need to be re-granted on each new machine.
7. **Bengali text in source:** Many strings are hardcoded in Bengali — no translation files exist.
8. **`_bhela_day_type` is a derived label, not stored truth.** Read it through `bhela_bm_booking_day_type()`. The raw meta is a cache and it went stale in production: a booking whose Travel Date was moved to a Monday kept printing "Weekend" on the invoice, because the only writers are the two repricing branches of `bhela_bm_save_booking()` and a booking taken online can reach neither.
9. **The stock lock is not only a wp-admin thing.** `includes/inventory-core.php` loads on *every* request and depends on nothing, because `wp_delete_post()` from WP-CLI or cron never reaches an `is_admin()` block — a closed month deletable from the command line is not closed. It closes four gaps the cost sheet still leaves open: direct `update_post_meta()`, trash, hard delete, and quick-edit. **A closed month cannot be deleted even by an administrator** — that is deliberate; reopen it first. A test fixture must therefore go through `bhela_test_delete()`, not `wp_delete_post()`.
10. **Staff salary is a cost, and gross profit deducts it.** `gross = trip profit − expenses − payroll`. It was omitted entirely until v2.27.0, which overstated every month's bottom line by the whole wage bill. Two rules: only a **saved** salary sheet counts (the roster alone is rates, not a commitment — an unsaved sheet must not deduct wages for a month nobody has done payroll for), and the figure deducted is `payable`, not `after`, because an advance already handed over is still part of the wage bill.
11. **`bhela_bm_salary_month_total()` and `bhela_bm_statement_data()` can call each other.** Payroll prices trip-based crew from the month's trip count, and that count comes from the statement — so the statement passes its own already-computed count down (`bhela_bm_salary_month_total( $month, count( $out['trips'] ) )`). `bhela_bm_salary_trip_count()` also carries a re-entry guard returning 0, because a wrong answer that returns beats an infinite loop that hangs the request. Do not remove either half.
12. **A ৳0 booking is not a paid one.** `bhela_bm_balance()` requires a positive total before it calls a balance settled — a Full Boat sits at ৳0 until an admin prices it, and `0 − 0 = 0` would otherwise stamp an unpriced enquiry PAID.

---

## 14. AI Assistant Instructions

When working on this project as an AI assistant:

### DO:
- **Read this file first** before making any changes
- **Use the graphify knowledge graph** (`graphify-out/`) for architecture questions
- **Follow WordPress coding standards** — proper escaping, nonce verification, capability checks
- **Keep pricing logic in sync** between `bhela-booking.php` (PHP) and `booking.js` (JS)
- **Prefix all functions** with `bhela_` (theme) or `bhela_bm_` (plugin)
- **Test changes locally** at http://bhela.local/
- **Run `graphify update .`** after modifying code files
- **Preserve existing comments** and docstrings unless specifically asked to change them

### DON'T:
- Don't use `Compress-Archive` for ZIPs
- Don't expose booking CPT data to REST API
- Don't add jQuery dependencies to frontend code
- Don't modify WP core files or third-party plugins
- Don't hardcode file paths with `c:\Users\User\...` — use WordPress functions (`plugin_dir_path()`, `get_template_directory()`, etc.)
- Don't break the theme ↔ plugin settings fallback chain
- Don't commit `.claude/settings.local.json` to git

---

## 15. Quick Commands

Run these from anywhere inside the repo — they are deliberately path-free, so they cannot
end up pointed at the wrong LocalWP site (see §5.1).

```powershell
# Check git status
git status --short

# View recent commits
git log --oneline -10

# Pull latest from GitHub
git pull origin main

# Push to GitHub
git push origin main

# Run the regression suite (thirteen harnesses)
php tests/run.php

# Validate JS syntax
node --check plugins/bhela-booking/assets/booking.js

# Update knowledge graph
graphify update .

# Check site is running
curl -s -o /dev/null -w "site: %{http_code}\n" --max-time 12 "http://bhela.local/"
```

If you need the repo root as a variable (building release ZIPs, for instance):

```powershell
$root = (git rev-parse --show-toplevel).Replace('/', '\')
```

---

*BHELA – The Haor Exclusive · "ভেলার আকর্ষণ ভেলা নয়, হাওর!" · Built by 3s-Soft*
