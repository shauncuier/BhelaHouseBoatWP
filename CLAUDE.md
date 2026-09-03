# BHELA — Project Intelligence (CLAUDE.md)

> **Purpose:** This is the canonical context document for AI assistants (Claude Code, Gemini, etc.) working on the BHELA WordPress project.
> Commit this file to GitHub so it's available on any machine you clone to.
>
> Last updated: 2026-09-01 · Theme & Plugin v2.38.0 (single shared version)

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
│   │   ├── costs-core.php           ← The approved-sheet lock. Loads on EVERY request (see §13.39)
│   │   ├── income.php               ← Trip income heads. Fill any and the sheet's earnings ARE their sum
│   │   ├── trip-report.php          ← Trip P&L (one trip end to end) + Revenue by Source
│   │   ├── seasons.php              ← Named date ranges. A label over a range, never a second boundary
│   │   ├── valuation.php            ← What BHELA is worth. Share value is DERIVED; the share count is snapshotted
│   │   ├── valuation-core.php       ← The lock on an approved valuation and a committed issue. Every request
│   │   ├── share-issue.php          ← Pre-money → post-money. Whole shares, honest dilution
│   │   ├── valuation-admin.php      ← The two screens and the valuation CSV
│   │   ├── investor-payreq.php      ← Payment requests: the second signature before money moves
│   │   ├── investor-dashboard.php   ← The investor dashboard, and the register/ledger/fund exports
│   │   ├── inventory-core.php       ← Stock post types + the lock. Loads on EVERY request (see §3.8)
│   │   ├── inventory.php            ← Stock lists, quantity model, monthly carry-forward, close workflow, screens
│   │   ├── inventory-import.php     ← Column-mapped CSV importer: upload → map → dry run → commit
│   │   ├── menu.php                 ← The four admin menus: group registry, URL helper, legacy shim (§3.9)
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
│   ├── *-test.php                   ← 16 headless harnesses
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
- **Full Boat arrives priced, not blank.** A whole-boat request from the booking form is
  priced at the standard rate for every cabin at maximum occupancy — `bhela_bm_full_boat_plan()`
  gives 6 cabins × 6 adults = 36 people — and an admin adjusts the Total after negotiating. It
  used to arrive at ৳0 waiting for a hand quote, so the guest saw no number and the booking sat
  unpriced. Weekend ৳288,000 / weekday ৳230,400 at current rates. The plan is fed through
  `bhela_bm_calc_multi()` rather than multiplied inline, so weekday discounts, holidays and the
  advance percentage apply exactly as they do to any other booking. `booking.js` computes the
  same figure the same way (`MAX_CABINS * MAX_CAP * occRate(MAX_CAP, dt)`) — `booking-test.php`
  §3d pins both sides together. The per-cabin `lines` breakdown is dropped: six identical rows
  is not what a whole-boat guest agreed to.
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
| `bhela_bm_settings` | All business settings (phones, payment details, advance %, invoice prefix, weekend days, holidays, email/SMS config, `vessel_reg`, `offer_*`) |
| `bhela_bm_dist_runs` | `YYYY-MM` => committed distribution run. **This option is the one-run-per-month constraint** |
| `bhela_bm_coupons` | Coupon codes. `uses` / `_used_by` are a **ledger**, carried across saves and never posted by the form |
| `bhela_bm_income_heads` | Owner-edited trip income heads. A slug is **frozen** — every saved sheet's figures hang off it |
| `bhela_bm_seasons` | Owner-named date ranges. Nothing is ever stored *against* a season, so deleting one only removes a way of grouping |
| `bhela_bm_settings` → `inv_*` | Share structure. `inv_total_shares` is **written only by a committed share issue**; the settings screen renders it read-only and reports drift rather than accepting an edit |
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

### 3.9 Four admin menus, and why the parent is asked for rather than written down

Everything used to hang off one **Bookings** menu — 22 rows, from Add New Booking to Audit Trail
to Quick Guide. `includes/menu.php` splits that into four, grouped by the job someone is doing:

| Menu | Slug / landing page | Rows |
|---|---|---|
| **Bookings** | `edit.php?post_type=bhela_booking` | All Bookings · Add New · 📊 Dashboard · 📄 Trip Report · 📅 Trip Calendar · ⭐ Reviews |
| **Accounts** | `bhela-bm-statement` | 🧾 Cost Sheets · 💸 Expenses · 👷 Salary · 📈 Monthly Statement · 📚 Yearly Report · 🤝 B2B Report · 🧮 Trip P&L · 💹 Revenue by Source |
| **Store** | `bhela-bm-inv-month` | 📦 Item Register · 🚚 Import Register · 🔧 Monthly Stock · 📐 Inventory Report · 🏷️ Asset Report · 🔩 Audit Trail |
| **Setup** | `bhela-bm-settings` | ⚙️ Settings · 👥 Team · 🗺️ Spots · 🖼️ Gallery · ⬆️ Bulk Upload · 📋 Activity Log · 🎯 Quick Guide |

Each group's `slug` is a real screen rather than an index page nobody maintains, so the parent
row and its first child collapse into one. **Where clicking the parent lands is a separate
question:** `_wp_menu_output()` takes a top-level's href from its *first submenu row*, not from
the parent's own slug — so Accounts opens Cost Sheets and Store opens Item Register. That is why
All Bookings is listed first under Bookings; with 📊 Dashboard first, clicking "Bookings" went to
`admin.php?page=bhela-bm-dashboard` instead of the booking list. `bhela_bm_menu_landing()` reports
the real landing slug and `ui-test.php` §9 pins all four. Bookings deliberately did not move:
`post_type=bhela_booking` appears in built URLs, form inputs and URLs already sent to customers.

Five functions carry the whole thing, and every one of them exists because the alternative
fails quietly:

| Function | Why it is not a literal |
|---|---|
| `bhela_bm_menu_groups()` | The registry. `caps` is an **OR** — hold any one and the menu appears. `add_menu_page()` takes a single capability and cannot express that, so the OR is evaluated at `admin_menu` |
| `bhela_bm_menu_parent($group)` | What every `add_submenu_page()` call passes. Falls back to the Bookings parent when a group is hidden, never `''` — an empty parent orphans the page instead of hiding it |
| `bhela_bm_menu_layout()` | Parent ⇒ ordered slug list. One list doing two jobs: display order, and **which group owns a page**, which is what the URL helper and the redirect shim read |
| `bhela_bm_admin_url($page,$args)` | Returns `edit.php?post_type=…&page=…` for a Bookings page, `admin.php?page=…` otherwise. ~25 call sites used to hand-build these; a wrong one does not error, it just goes somewhere else |
| `bhela_bm_menu_legacy_redirect()` | Permanent, not transitional — `emails.php` and `sms.php` have **already** put `edit.php?post_type=bhela_booking&page=bhela-bm-settings` into sent mail |

**Visibility is decided at `admin_menu`, and it has to be.** `admin_menu` runs after the current
user resolves; `init` does not, so `show_in_menu` can never ask `current_user_can()`. That is why
the seven CPT rows are *moved* at priority 20 rather than registered against the right parent in
the first place — their parent is fixed at `init`, before there is a user to ask about. Moving
re-adds the existing row, keeping the emoji that lives in `labels['all_items']`.

Splitting the menus also fixed a live bug: `bhela_bm_inv_menu()` added the four store screens
under Bookings unconditionally while the old standalone menu removed only the CPT row, so a
storekeeper or cost-checker saw **Monthly Stock, Inventory Report and Asset Report twice**.
`tests/ui-test.php` §9 now pins that shut — no slug under two parents, and none twice under one.

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
| `bhela_bm_calc_multi($cabins,$date,$coupon,$phone)` | `includes/frontend.php` | Authoritative multi-cabin pricing, discounts included |
| `bhela_bm_offer($date)` | `bhela-booking.php` | Is a promotion running, and at what %. Returns **both** percentages — see §13.28 |
| `bhela_bm_offer_rate($row,$day_type,$date)` | `bhela-booking.php` | The per-person rate charged. **`min(standing, offer)`** — an offer must never raise a price |
| `bhela_bm_split_by_shares($pot,$holders,$divisor)` | `includes/investors.php` | Largest-remainder split. Divides by the **configured** share total — see §13.30 |
| `bhela_bm_share_totals()` | `includes/investors.php` | Issued vs configured. **Reports, never corrects** |
| `bhela_bm_dist_preview($month,$pct)` | `includes/distribution.php` | Pure. What a month WOULD pay. The screen shows this and commits exactly it |
| `bhela_bm_dist_commit($month,$pct,$note)` | `includes/distribution.php` | Writes the run + one ledger row each. Once per month, ever |
| `bhela_bm_ledger_add($args)` | `includes/investor-ledger.php` | The ONLY ledger writer. Append-only |
| `bhela_bm_ledger_reverse($row,$reason)` | `includes/investor-ledger.php` | A contra row. Nothing is ever edited or deleted |
| `bhela_bm_fund_ledger($fund,$from,$to)` | `includes/funds.php` | Reserve/management. Balance replayed, never stored |
| `bhela_bm_fund_allocate_run($run)` | `includes/funds.php` | The ONLY source of an allocation — see §13.35 |
| `bhela_bm_cashflow($from,$to)` | `includes/cashflow.php` | Cash moved, not profit earned. Reads existing stores only |
| `bhela_bm_current_investor()` | `includes/investor-portal.php` | **The portal's whole security model.** Takes no id — see §13.34 |
| `bhela_bm_investor_position($id)` | `includes/investor-ledger.php` | Balance **replayed from rows**, never stored |
| `bhela_bm_discount($rack,$offer,$date,$coupon,$phone)` | `includes/coupons.php` | Offer **or** coupon, never both. The larger single discount wins |
| `bhela_bm_coupon_check($code,$total,$date,$phone)` | `includes/coupons.php` | Read-only. **Never redeems** — see §13.27 |
| `bhela_bm_coupon_redeem($code,$phone,$id)` | `includes/coupons.php` | The ONLY writer to `uses`. Called once, at booking insert |
| `bhela_bm_balance($total,$paid)` | `bhela-booking.php` | Due + `settled` — the one reading every guest-facing surface shares |
| `bhela_bm_booking_day_type($id)` | `bhela-booking.php` | Day type derived from the travel date; the stored meta is only a fallback |
| `bhela_bm_booking_stay($id)` | `bhela-booking.php` | Check-in / check-out + time windows. **Derived every read**, never stored — same trap as §13.8 |
| `bhela_bm_confirm_text($id)` | `includes/confirm.php` | The WhatsApp confirmation message. Template is a setting; blank lines are dropped |
| `bhela_bm_pay_methods()` | `bhela-booking.php` | Booking payment methods, key => label. One list; expenses keep their own |
| `bhela_bm_agencies($retired)` | `includes/agencies.php` | B2B partner directory. Ids frozen once, retired ones still resolve on old bookings |
| `bhela_bm_commission_rows($from,$to)` | `includes/agencies.php` | Commission owed in a date range, by agency. **The single source** — the cost sheet and the statement both read it |
| `bhela_bm_b2b_rows($from,$to,$agency)` | `includes/b2b-report.php` | Every agency booking in a range. Deliberately shows what `bhela_bm_commission_rows()` hides — unconfirmed and cancelled |
| `bhela_bm_b2b_range($from,$to)` | `includes/b2b-report.php` | Blank dates mean EVERY date. One resolver, because the page and the CSV both need it — see §13.24 |
| `bhela_bm_report_date($value)` | `bhela-booking.php` | The one Y-m-d validator. In core because four modules need it — see §13.22 |
| `bhela_bm_cost_b2b_drift($id)` | `includes/costs.php` | Whether the B2B line has gone stale. **Reports, never corrects**, like the earnings drift beside it |
| `bhela_bm_agency_ref_url($id)` | `includes/agencies.php` | A partner's referral link. Token is **stored and random**, so it can be rotated — a `wp_hash()` over the frozen id never could |
| `bhela_bm_referred_agency()` | `includes/agencies.php` | The agency in the visitor's cookie, or '' |
| `bhela_bm_full_boat_label()` | `bhela-booking.php` | The whole-boat `_bhela_cabin_type` string — one copy for admin and form |
| `bhela_bm_sanitize_weekend_days($raw)` | `bhela-booking.php` | `date('w')` numbers, whitelisted 0–6 (Sunday is 0, so the filter is explicit) |
| `bhela_bm_invoice_data($id)` | `includes/invoice.php` | Everything the printable template needs — split out so the invoice is renderable in a test |
| `bhela_bm_csv_cell($value)` | `bhela-booking.php` | Neutralises a cell a spreadsheet would execute. **Every free-text export cell goes through it**; never a figure |
| `bhela_bm_audit($args)` | `includes/audit.php` | The ONLY writer to the audit table, and it only inserts. See §3.7 |
| `bhela_bm_income_heads($retired)` | `includes/income.php` | Trip income sources in force. Same retirement rule as the cost heads |
| `bhela_bm_income_read_post($posted)` | `includes/income.php` | One place where "the heads ARE the earnings" lives. Unknown keys dropped, never stored |
| `bhela_bm_cost_income($id)` | `includes/income.php` | A sheet's income by head. **Empty means the sheet does not use them** — which is what keeps every approved sheet's value unchanged |
| `bhela_bm_income_rows($from,$to)` | `includes/income.php` | Income by head over a range, approved sheets only. `unsplit` is named, never folded into "Other" |
| `bhela_bm_trip_report($id)` | `includes/trip-report.php` | One trip end to end. Its `share` block is an **apportionment, not a record** — see §13.41 |
| `bhela_bm_revenue_by_source($from,$to,$period)` | `includes/trip-report.php` | Revenue by head, grouped by day/month/year |
| `bhela_bm_cost_locked($id)` | `includes/costs-core.php` | Is this an approved sheet. Loaded on every request, reads meta directly — see §13.39 |
| `bhela_bm_cost_locked_keys()` | `includes/costs-core.php` | The meta a locked sheet refuses. `_bhela_cost_status` is deliberately absent — unlock must stay possible |
| `bhela_bm_dist_locked($id)` | `includes/distribution-core.php` | Run, ledger row **or fund row**. `bhela_fund` was missing from it for two releases — see §13.54 |
| `bhela_bm_trip_rows($from,$to)` | `includes/trip-report.php` | The five columns the P&L list draws. **Never `bhela_bm_trip_report()` per row** — see §13.52 |
| `bhela_bm_trip_date_bound($which)` | `includes/trip-report.php` | Earliest/latest trip date on record. A blank filter's bound comes from the data, never a sentinel — §13.51 |
| `bhela_bm_trip_undated_sheets()` | `includes/trip-report.php` | Sheets a date range cannot match. Named on screen rather than silently absent |
| `bhela_bm_season_overlaps()` | `includes/seasons.php` | Overlapping season pairs. Reports, never refuses — the earliest start wins |
| `bhela_bm_cost_status_tone($status)` | `includes/ui.php` | Cost-sheet status → pill tone. In `ui.php` on §13.22 grounds, preventatively |
| `bhela_bm_payreq_limit()` | `includes/investor-payreq.php` | Listing cap. The pending TOTAL counts in SQL instead — a truncated total understates money owed |
| `bhela_bm_investor_notice($result)` | `includes/investor-admin.php` | Carries a `WP_Error` from a guard to the next page load. A correct policy that looks like a broken form is worse than none |
| `bhela_bm_cost_meta_write($id,$k,$v)` | `includes/costs-core.php` | The only legitimate way to write a locked sheet's figures |
| `bhela_bm_payreq_add($args)` | `includes/investor-payreq.php` | Raise a payment request. Writes NO ledger row and moves no money |
| `bhela_bm_payreq_approve($id)` | `includes/investor-payreq.php` | Approve, and only now write the ledger row. **Refuses the requester** — see §13.40 |
| `bhela_bm_seasons()` | `includes/seasons.php` | The owner's seasons. A season with no resolvable range is not a season |
| `bhela_bm_season_investors($key)` | `includes/seasons.php` | Per-investor declared/paid **inside** a season. Not a lifetime balance |
| `bhela_bm_investor_dash_data()` | `includes/investor-dashboard.php` | Everything the dashboard draws, split out so the figures are assertable |
| `bhela_bm_share_value($val)` | `includes/valuation.php` | What one share is worth. **Falls back to `inv_per_share`** when nothing is approved — that fallback is the whole compatibility story |
| `bhela_bm_valuation_current($reset)` | `includes/valuation.php` | The latest APPROVED valuation, or null. A draft is nobody's baseline |
| `bhela_bm_valuation_history($approved)` | `includes/valuation.php` | Every valuation with growth against its predecessor; the earliest against `inv_total_investment` |
| `bhela_bm_investor_holding($id,$val)` | `includes/valuation.php` | Cost basis, holding value, appreciation. **Never merged into `bhela_bm_investor_roi()`** — see §13.58 |
| `bhela_bm_holding_totals()` | `includes/valuation.php` | Every investor's capital position on one valuation read, not N |
| `bhela_bm_share_issue_preview($shares,$target)` | `includes/share-issue.php` | Pure. What a round WOULD do; the screen shows this and the commit writes exactly it |
| `bhela_bm_share_issue_commit($args)` | `includes/share-issue.php` | **The only sanctioned writer of `inv_total_shares`** |
| `bhela_bm_share_issue_drift()` | `includes/share-issue.php` | Configured total vs the issue history. Reports, never corrects |
| `bhela_bm_val_delete($id)` | `includes/valuation-core.php` | The only sanctioned delete of a locked record. One caller: the commit's abort — see §13.65 |
| `bhela_bm_share_issue_valuation_map()` | `includes/share-issue.php` | Valuation ⇒ issue, built once. The per-row lookup was a full query per row |
| `bhela_bm_val_locked($id)` | `includes/valuation-core.php` | Approved valuation (state) or any share issue (from birth). Loads on every request |
| `bhela_bm_portal_login_limit()` | `includes/investor-portal.php` | Failed portal sign-ins allowed per IP per hour. Filterable, 8 |
| `bhela_bm_inv_line_check($line)` | `includes/inventory.php` | The quantity invariant: `good+rep+ur+dam === close`. Reports a mismatch, never rebalances it |
| `bhela_bm_inv_line_key($item,$loc)` | `includes/inventory.php` | The line key. Returns the item ID today; the one place to change if stock ever splits by location |
| `bhela_bm_inv_period_id($month,$create)` | `includes/inventory.php` | Resolves — or mints, behind an `add_option` mutex — the one record for a month |
| `bhela_bm_inv_take_opening($id,$force)` | `includes/inventory.php` | Snapshots the predecessor's closings. A snapshot, not a live read, so a closed month keeps its figures |
| `bhela_bm_inv_opening_drift($id)` | `includes/inventory.php` | Whether the month underneath moved since. **Reports, never corrects** |
| `bhela_bm_inv_month_data($month,$filter)` | `includes/inventory.php` | Everything a stock screen draws. Totals come from the **rows it is about to draw**, not the stored totals meta — see §13.16 |
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
| `bhela_bm_salary_rows($id,$month,$trips,$roster)` | `includes/salary.php` | Payroll rows; trips default from approved sheets. `$roster` **false for any figure that adds up** — see §13.10 |
| `bhela_bm_cost_transitions()` | `includes/costs.php` | Cost-sheet workflow: from-state → target + required capability |
| `bhela_bm_screen_header()` | `includes/ui.php` | The teal banner every admin screen opens with (also emits `.wp-header-end`) |
| `bhela_bm_status_pill()` | `includes/ui.php` | One pill for every status vocabulary — five tones × solid/soft |
| `bhela_bm_status_tone()` | `includes/ui.php` | Booking status → tone + weight |
| `bhela_bm_is_plugin_screen()` | `bhela-booking.php` | True on a BHELA admin screen; gates `admin.css` and the body class |
| `bhela_bm_menu_groups()` | `includes/menu.php` | The four menus and the capabilities that make each worth showing (OR, not AND) |
| `bhela_bm_menu_parent($group)` | `includes/menu.php` | The parent every `add_submenu_page()` call passes. Never returns `''` |
| `bhela_bm_menu_layout()` | `includes/menu.php` | Parent ⇒ ordered slugs. Also the ownership map the URL helper and shim read |
| `bhela_bm_admin_url($page,$args)` | `includes/menu.php` | **The only way to build a link to a plugin screen.** Knows which parent a page hangs under |
| `bhela_bm_menu_legacy_redirect()` | `includes/menu.php` | Keeps `edit.php?post_type=…&page=…` alive — that shape is already in sent email |
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
4. Pick a group and register against it: `add_submenu_page( bhela_bm_menu_parent( 'accounts' ), … )`.
   Never hardcode a parent — see §3.9 for the four menus and why the parent is asked for
5. Add the slug to that parent's list in `bhela_bm_menu_layout()`, or it sorts to the end
6. Build every link to it with `bhela_bm_admin_url( $slug, $args )` — never a hand-built
   `edit.php?post_type=…` string, which is what §3.9's shim exists to clean up after
7. If the page has a GET filter form, it must **not** carry a hidden `post_type` input unless
   the page is under Bookings. That is the silent failure: the filter lands on the Posts list
8. Give the menu item an emoji **no other item uses** — `tests/ui-test.php` §9b reads the real
   menu and fails on a duplicate or a missing one
9. If a genuinely new component is needed, name it `bha-` and add it to `assets/admin.css`

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

Sixteen headless harnesses: security, the July 2026 statement reproduced to the taka, salary,
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

- [ ] `php tests/run.php` passes — all sixteen harnesses
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
10. **Staff salary is a cost, and gross profit deducts it.** `gross = trip profit − expenses − payroll`. It was omitted entirely until v2.27.0, which overstated every month's bottom line by the whole wage bill. Three rules: only a **saved** salary sheet counts (the roster alone is rates, not a commitment — an unsaved sheet must not deduct wages for a month nobody has done payroll for); within a sheet only the rows **saved onto it** count, so hiring someone today cannot add a wage bill to a month already paid (`bhela_bm_salary_rows()` takes `$include_roster` — **true for the form, false for anything that adds up**; one new monthly-salaried manager silently cost July ৳25,000 before this); and the figure deducted is `payable`, not `after`, because an advance already handed over is still part of the wage bill.
11. **`bhela_bm_salary_month_total()` and `bhela_bm_statement_data()` can call each other.** Payroll prices trip-based crew from the month's trip count, and that count comes from the statement — so the statement passes its own already-computed count down (`bhela_bm_salary_month_total( $month, count( $out['trips'] ) )`). `bhela_bm_salary_trip_count()` also carries a re-entry guard returning 0, because a wrong answer that returns beats an infinite loop that hangs the request. Do not remove either half.
12. **A ৳0 booking is not a paid one.** `bhela_bm_balance()` requires a positive total before it calls a balance settled — a Full Boat sits at ৳0 until an admin prices it, and `0 − 0 = 0` would otherwise stamp an unpriced enquiry PAID.
13. **Never put an emoji in a top-level `$menu_title`.** `$admin_page_hooks[$slug]` is `sanitize_title($menu_title)`, and `sanitize_title('📦 Store')` is `'%f0%9f%93%a6-store'` — so every child's screen id becomes `%f0%9f%93%a6-store_page_bhela-bm-inv-month`. Accounts, Store and Setup therefore take **emoji-free titles with dashicons**, which is WordPress's own top-level convention. Emoji belong on submenu rows, which is where this plugin's convention and its tests both live. `ui-test.php` §9 asserts the three hooks are `accounts`, `store`, `setup`.
14. **A GET filter form under an `admin.php` parent must not resubmit `post_type`.** Every filter form used to carry `<input type="hidden" name="post_type" value="bhela_booking">` because its page was a child of `edit.php`. Left in place on a moved page, the filter submits to the **Posts list** — no PHP error, no warning, just the wrong screen. Only `reports.php` still carries it, because Trip Report is the one filter form still under Bookings.
16. **A stock screen totals the rows it is drawing, not the `_bhela_inv_totals` meta.** That meta is written only on save, so a month that had just been opened showed Opening 0 / Closing 0 / ৳0 on every summary card, directly above rows carrying the 56 and 12 it had inherited. The same gap opens mid-month for any item nobody has touched yet: its opening is real but sits in no stored line. `bhela_bm_inv_month_data()` therefore computes totals from `$rows`, which is what the monthly sheet, both reports, the CSV and the print view all read. For a closed month the rows *are* the stored lines, so it still agrees with the frozen figures to the taka — `bhela_bm_inv_period_totals()` is left alone for exactly that reason.
20. **A referral attributes and suggests; it never spends.** A booking arriving through `?ref=` records the agency and a suggested commission but is marked `unconfirmed`, and `bhela_bm_commission_rows()` skips those — so it reaches neither the Monthly Statement nor the cost sheet until a person confirms it on the booking. Without that gate, appending `?ref=` to a URL would move money, and the partner who owns the link is the one best placed to claim bookings that were coming anyway.
21. **`?ref=` sets a cookie and redirects, and the redirect is load-bearing.** A cached page never runs PHP, so a handler that only set the cookie in place would pass in testing and silently stop attributing in production — see `docs/CACHING.md`, which is about exactly that failure.
18. **B2B commission is invisible to the guest, by design.** It is a commercial arrangement between BHELA and the partner, so it must never appear on the invoice, the customer email or the confirmation message — `booking-test.php` §3f asserts its absence from all three, because a rule about what must NOT be there breaks silently. `{agency}` / `{commission}` / `{agency_ref}` placeholders exist so an *agency-facing* template can carry them, and are deliberately absent from the shipped guest template.
19. **A commission is deducted once, from the booking.** `bhela_bm_commission_rows()` is the single source: the Monthly Statement deducts from it, and the trip cost sheet's `b2b_partner` line **fills itself** from it. Typing the same figure onto the cost sheet by hand would deduct it twice — so a hand-typed line is left alone and simply stops being auto-filled, exactly as `_bhela_cost_earnings_auto` distinguishes a typed figure from a cached one.
17. **A new input on the booking form is not submitted until `booking.js` names it.** The submit handler copies an explicit field list into the request rather than serialising the whole form, so adding `<input name="address">` rendered the field, let the guest fill it in, and dropped the value on the way to the server — the booking stored `''` with no error anywhere. `booking-test.php` §3e pins both ends: the form renders the input, and the JS list contains it.
24. **A report whose date default hides its own subject fails silently.** The B2B Report shipped defaulting to the current calendar month, filtered by *travel* date — but a referral is taken now for a trip months away, so confirming one changed nothing visible: three agency bookings existed and one was on screen, with no error to explain the other two. Blank dates now mean **every** date, and the operator narrows down instead of starting narrow. `bhela_bm_b2b_range()` is the single resolver because the CSV export had a worse version of the same bug — it passed the raw request through with no defaulting at all, so blank dates hit the early return in `bhela_bm_b2b_rows()` and downloaded an empty file. The waiting-referral count is deliberately computed **outside** the filter: a date window is the operator's choice, and a choice must not be able to hide the one thing the screen exists to surface.

25. **`vessel_reg` has ONE source, and the theme's fallback for it is deliberately empty.** `bhela_contact()` hardcodes the address and phone numbers so the theme still reads sensibly with the plugin off. The vessel registration is not treated that way: it is a legal identifier, and a stale hardcoded one **misrepresents the boat** while a missing one is merely missing. So the theme default is `''`, the real value comes from the plugin setting, and **every surface hides its own line when it is blank** — the invoice header, the footer identity badge, the booking trust strip and the JSON-LD `identifier`. `booking-test.php` §3j asserts the absence case and that the footer prints it exactly **once**; it is printed bare, with no invented label like "Govt. Reg:", which would risk naming the wrong kind of document. The `{vessel_reg}` placeholder exists but is deliberately **absent from the shipped confirmation template** — a blank `confirm_template` means "use the shipped default", so an edit there would never reach a site that has saved its settings once.
26. **`bhela_bm_settings` was not in `bhela_test_owner_options()` until a harness needed to write to it.** That is the wrong order to discover it: the option holds every phone number, payment detail, email/SMS template and gateway key the owner has ever typed, and a harness that cleared it would have wiped all of them. The guard now also covers `bhela_bm_rates`, `bhela_bm_trips`, `bhela_bm_role_perms` and `bhela_bm_inv_seq` — the whole class rather than the three instances that had already bitten (§13.9's period index, the agency directory, and this).

37. **`bhela_test_isolate()`'s post-type list is not optional, and it is not enough on its own.** A type missing from it makes a harness read the REAL database: the four investor types were absent at first, so seeding four demo investors on the dev site pushed issued shares over the configured total and 21 assertions failed at once, looking like a distribution bug. But `bhela_dist` and `bhela_fund` must NOT be in it either — the plugin titles those rows itself ("reserve allocation 29470"), so the `post_title LIKE 'ZZ%'` scope would hide them from the very harness that created them. Only types whose titles a fixture controls belong in the list; the rest the harness scopes by its own run id.
38. **A money harness must measure DELTAS, not absolutes.** `investor-test.php` asserted a reserve balance of exactly 30,000, which is an assertion about the database rather than the code — it broke the moment the site carried a real distribution. It now snapshots both fund balances before committing and asserts the change. Its cleanup matches on a note CONTAINING `ZZ`, not starting with it: a reversal's note reads `#123 বাতিল — ZZ wrong head`, so a prefix test left every reversal adjustment behind and the reserve grew by ৳12,000 on **each** run of the suite. Three consecutive suite runs now leave the data byte-identical, which is the only proof that actually counts.

35. **A fund allocation comes from a distribution, and only from one.** `bhela_bm_fund_add()` refuses an `allocation` with no run id: the reserve exists *because* a percentage was taken off a month, so a hand-typed one would create money the business never earned. Spending is the opposite — a person doing it — so it is entered, and reversed with a contra row when wrong. An **allocation cannot be reversed at all**: it is the arithmetic of a committed month, and cancelling it here would leave the run saying one thing and the fund another. Reverse the run instead. Overdrawing a fund is **recorded, not blocked** — the spending happened, and refusing the entry just moves the error somewhere the books cannot see — but the screen says so loudly.
36. **Cash flow is not the Monthly Statement in another hat.** The statement answers "did we trade profitably", an accrual question where an approved cost sheet counts whether or not the supplier has been paid. `bhela_bm_cashflow()` answers "did money move", and a business can be profitable and short of cash at once. Two consequences that are easy to get wrong: a fund **allocation is never cash out** (it is an internal earmark, and counting it double-counts against the trip costs it later pays for), and a **reversed investor payment is not cash out** either — money handed back never left. Guest payments key on the payment date, falling back to the travel date: a booking paid in June for an October trip is June's cash.

34. **The investor portal scopes at the resolver, not in the UI.** `bhela_bm_current_investor()` takes **no parameter**: it maps the logged-in user id to a record and there is no way to ask about anybody else, so the "change the number in the URL" attack has nothing to change. `bhela_bm_portal_data()` takes none either. Two records claiming one login makes it **refuse** rather than pick whichever sorted first, because that would decide whose money a person sees. Its cache is keyed by user id — an unkeyed static would hand back the previous user's record whenever `wp_set_current_user()` changes mid-request (cron, WP-CLI). The `bhela_investor` role holds **`read` and nothing else**; granting even "view investors" would let one investor read another's bank details. The portal is **read-only** — a disputed figure is corrected by the office with a reversal, which is what makes the ledger's append-only rule worth having. Verified live: URL tampering with `investor`/`user`/`post` ids, wp-admin (302 or 403), `?p=`, and the REST route all refuse.

30. **Investor money is derived from the approved cost sheets, and the divisor is the CONFIGURED share total.** `bhela_bm_dist_preview()` reads `bhela_bm_statement_data()['gross']`, so the existing prepare → check → approve chain is already the gate: **an unapproved sheet pays nobody**, with no second workflow invented. The split then divides by `inv_total_shares`, not by the sum of the holders — it shipped dividing by the holders and gave three investors holding 35 of 115 shares the *entire* pool, 3.3× what a share is worth, silently contradicting `bhela_bm_investor_share_pct()` which had always divided by 115. Unissued shares keep their portion; `unallocated` reports it. Allocation is **largest-remainder** so the parts equal the whole to the taka — 115 independently rounded figures do not sum to the pool, and losing ৳7 a month stops a ledger reconciling inside a season.
31. **A committed distribution and its ledger rows cannot be deleted — by anyone, including an administrator.** `distribution-core.php` loads on **every** request, not behind `is_admin()`, for the reason §13.9 gives: `wp_delete_post()` from WP-CLI or cron never reaches an admin-only guard. A wrong run is corrected with a **reversal**, never an erasure, because "I deleted it" leaves no record of why the figures moved. A test fixture goes through `bhela_test_delete()`, which opens the plugin's own window rather than weakening the guard. **A reversed payment also stops counting toward `received` and ROI** — it corrects the balance on its own, but ROI is computed from money received, so without that the investor's return stays permanently overstated.
32. **A harness must state the configuration it asserts against.** Three separate failures this release were a test inheriting the owner's live settings: the Full Boat rate against a running promotion, the standing-rate assertions, and every front-end submit test the moment OTP was switched on — all of which read as pricing regressions. `booking-test.php` now sets `otp_enabled` and the offer state it needs and restores both. `bhela_bm_settings` is in `bhela_test_owner_options()`, so writing to it is safe; inheriting it is not.
33. **A regex with a literal newline in it asserts about line endings, not about code.** `ui-test.php` §8 embedded one to match the money font stack, and failed the moment `admin.css` was rewritten with LF while the test file kept CRLF. It says `\s+` now. Worth knowing generally: editing a CRLF file with a tool that reads universal newlines and writes back rewrites the whole file's endings.

27. **Discounts are a layer over the rates, and there are exactly two of them.** The promotional offer (`offer_*` settings) and coupon codes both **read** `bhela_bm_rates` and never write to it — editing the rate table to run a promotion destroys the rack rate, leaving nothing to restore and no "was ৳40,000" to strike through. Both percentages come off **`regular`**, so a 20% weekend offer prices a weekend at exactly today's weekday rate; that is arithmetic, not a bug. **`bhela_bm_offer_rate()` returns `min(standing, offer)`** because the weekday rate is already 20% under regular — any weekday offer below 20% would otherwise *raise* the price, which is the worst thing this feature could do. An offer and a coupon **never combine**: `bhela_bm_discount()` compares both in taka against the rack total and applies the larger one once, so a 40% coupon during a 30% offer sells at 40% off, not 58%. **Checking a coupon must never redeem it** — `bhela_bm_coupon_check()` is read-only and `bhela_bm_coupon_redeem()` runs once at booking insert, or a guest pressing Apply twice would exhaust a "first 20 bookings" code without booking anything.
28. **`bhela_bm_offer()` returns BOTH percentages, because a dateless caller has two columns to fill.** It used to derive its day type from the date it was given and return only that one percentage — so the cabins page and the settings preview, which show a weekend and a weekday column side by side with no single date behind either, asked for `'weekday'` and silently got the weekend figure. The cabins page advertised a 20% weekday discount while the booking form charged 30%. `bhela_bm_offer_rate()` now keys on the `$day_type` **argument**, never on the date's own day type, and `booking-test.php` §3l pins the dateless call specifically — the dated one passed throughout.
29. **The JS "rack" price must bypass the offer.** `booking.js` computed its struck-through original with `occRate(tier,'weekend')`, which now applies the promotion — so the discount was measured against itself and printed a badge reading −13% for a 30% offer. `rackRate()` exists to return `r.regular` untouched, mirroring `$reg = (int) $row['regular']` in `bhela_bm_calc_multi()`. Only rendering the real form caught it; the harnesses call data functions and never draw a page.

22. **A shared helper parked in one screen's file is a load-order accident.** `bhela_bm_report_date()` sat in `reports.php` while four modules validated dates with it. Two had already worked around that: `costs.php` carried a duplicate behind a `function_exists()` guard, and `b2b-report.php` fataled outright when the report module was not loaded. It lives in `bhela-booking.php` now, `bhela_bm_cost_date()` forwards to it, and there is one implementation.
23. **The B2B Report is the one screen that must NOT reuse `bhela_bm_commission_rows()`.** That function answers "what does the month owe", so it drops cancelled bookings and unconfirmed referrals on purpose (§13.20). The report exists to show exactly those — a referral waiting on a person is the main reason to open it. They are therefore two readings of the same data, and `booking-test.php` §7 pins their *owed* figures against each other to the taka, because two implementations of one rule is how a silent disagreement starts.

15. **`wp_set_current_user()` returns the cached user when the id has not changed.** Swapping a role on the same account and re-setting it keeps the old capabilities, so a per-role test reports identical menus for every role and passes by luck. Go via `wp_set_current_user( 0 )` first — `ui-test.php`'s `zz_menu()` does, and three role assertions were silently wrong until it did.

39. **A one-off cost row used to vanish on the *second* save, and it was a key collision.** `bhela_bm_cost_lines()` offers five spare slots named `new_0…new_4`. A row typed into the first is stored under that slug — `new_0` — and the generator, which counted only *empty* rows, then emitted a **fresh blank `new_0`** after it. The form carried two inputs with one name, the browser sent both, PHP kept the last, and the last was the empty one: the row and its money disappeared off the trip's total. It looked intermittent because it only bit on the save *after* the one that stored the row, and a sheet with five one-offs lost **all five**. The generator now skips any key already on the sheet, and the JS "+ Add row" key carries a counter as well as a timestamp because two clicks inside one millisecond collided the same way. `roundtrip-test.php` §7 pins it, and was verified to fail against the old code.
40. **`bhela_bm_cost_save()` guarded the metabox, and only the metabox.** §13.9 said as much when the stock lock was built — the cost sheet still left a direct `update_post_meta()`, trash, hard delete and quick edit wide open. That was a documented shortcoming while an approved sheet only fed a report; it stopped being one when the investor chain started reading approved sheets and nothing else. `bhela_bm_dist_preview()` takes its gross from `bhela_bm_statement_data()`, which sums approved sheets, and a committed distribution is immutable — so a sheet deletable from WP-CLI leaves profit **declared owed to named people** against a trip the books can no longer show. `includes/costs-core.php` closes all four, loads on **every** request (§13.9's reason: `wp_delete_post()` from cron never reaches an `is_admin()` block) and reads the status meta directly because `costs.php` is admin-only. It shipped in v2.37.0 with only two of the three meta filters — see §13.49. `_bhela_cost_status` is deliberately **not** guarded: unlock is how a sheet is legitimately reopened, and a lock that cannot be lifted is a trap. A harness fixture therefore goes through `bhela_test_cost_meta()`, not `update_post_meta()`.
41. **Paying an investor needed no second signature.** Cost sheets have required prepare → check → approve for a long time; handing money to a named person needed one person. `includes/investor-payreq.php` adds `requested → approved → ledger row written`, and it is a **separate record, not a status on a ledger row** — the ledger's rows are immutable by design and that is the single property that makes them worth trusting a year later. `bhela_investor_approve` is a distinct capability held by Manager and Administrator and **not** by Investor Relations, who raise them; on top of that `bhela_bm_payreq_approve()` refuses when `(int) $r['by'] === get_current_user_id()`, because a second signature the same hand can supply is not a second signature. A pending request appears on the investor screen, the dashboard and the portal, and in **no** balance, ROI or cash-flow figure. An *adjustment* still records straight away: it is a correction that reverses cleanly, not somebody deciding to hand over cash.
42. **An audit trail that prints the old and new bank account is a second copy of the data it protects.** `bhela_bm_investor_save()` audited a shareholding change and nothing else, so an investor's account number could be repointed with no record at all — the highest-value tamper on the module. Every field is diffed and audited now, but the five fields in `bhela_bm_investor_secret_fields()` record **that** they changed, never the values: the trail is never pruned, has no clear button, and is readable by anyone who can open it. An ordinary field still carries its values, or the trail would be useless. The register CSV carries no account number and no NID either — an export leaves the building.
43. **The portal had no attempt limit, because WordPress core has none.** `bhela_bm_portal_login()` now throttles per IP on **failed attempts only** — a correct sign-in clears the counter, which matters behind the shared and CGNAT addresses most users here are on, or one attacker locks out a whole building. The throttled response is **byte-for-byte the wrong-password page**: saying "too many attempts" confirms the account exists and that the limit is worth waiting out. `investor-test.php` §17 asserts the two pages are identical rather than grepping the source, because the source comment explaining the rule matched the grep.
44. **Income heads are earnings, not a figure beside them.** Fill any head and `bhela_bm_cost_save()` sets `_bhela_cost_earnings` to their sum, and the earnings box stops being typeable — two editable places holding one number is how they start to disagree. A sheet with no heads stores nothing and behaves exactly as before, which is what lets this ship without changing a single approved sheet's value. Two live JS traps came out of rendering the real form: the box has to **hand back the figure that was in it** when the last head is cleared (clearing 90,000 then 12,000 left 12,000 sitting in an editable box — a number nobody typed, which would have been saved), and the date lookup seeds **Cabin booking** rather than the earnings box.
45. **A season is a label over a date range, and must never become a second source for period boundaries.** The Monthly Statement and Yearly Report keep computing exactly what they compute; a season only hands them a from/to. The moment a season could define its own month boundaries there would be two answers to "what did July make". None are shipped: inventing somebody else's season dates puts a confident wrong answer on the screen, so the screens say so and ask for one.
46. **The Trip P&L's contribution block is an apportionment, not a transaction.** A distribution is monthly — nothing anywhere says a given trip sent ৳4,000 to the reserve. What is true is that the trip made a stated share of the month's approved profit, so the same share of what that month distributed is attributable to it. It is computed only when the month has a committed run, and the screen says which it is in so many words.
47. **The ৳ scanner in `ui-test.php` §5 reads every grouped figure as money, which held only while the audit trail had under a thousand rows.** Auditing every investor field pushed it past that and "1,021 events recorded" failed an assertion about money columns. A deliberately unmoneyed figure is now marked `.bha-plain` and the scanner drops those first, so the assertion keeps meaning "every money figure" rather than "every figure".
48. **The investor screens were absent from `ui-test.php` §4 entirely.** Five screens rendering money, and nothing checked they render clean, carry the taka or keep their columns aligned — which is the gap that let a misaligned header ship across fourteen tables. All five are in the sweep now, along with Trip P&L, Revenue by Source and the investor dashboard.
49. **A lock needs all THREE meta filters, and `add_post_metadata` is the one that gets forgotten.** `update_post_meta()` on a key that does not exist yet is still caught by `update_post_metadata` — WordPress fires that filter before it checks existence — so a lock with only `update` and `delete` looks complete and tests green against any key that already exists. `add_post_meta()` fires `add_post_metadata` and nothing else. The window is narrow (a locked key is only reachable this way while it is **absent**) and it was live: `_bhela_cost_income` is absent on every sheet approved before income heads existed, and it is exactly what Trip P&L and Revenue by Source read, so an approved sheet's revenue breakdown was forgeable from WP-CLI. The same one-line omission was in `distribution-core.php`, on the ledger. `inventory-core.php` had all three from the start — the two later locks were written from a shortened reading of it. `roundtrip-test.php` §9 now deletes each locked key and probes `add_post_meta()` on the absent key, which is the only probe that can catch this; §8's original probe used keys that were already present and passed throughout.
50. **A read-then-write state check is not a workflow guard.** `bhela_bm_payreq_approve()` read the request, saw `requested`, wrote a ledger row, then set the state. Two approvals arriving together both passed the read and **both paid** — and nothing in the ledger looked wrong afterwards, because both rows were individually valid. The state is now claimed with a single conditional `UPDATE … WHERE meta_value = 'requested'`, so the database picks the winner and `rows_affected` reports it; `wp_cache_delete()` follows because the write went round the meta API. Same discipline as the `add_option()` mutex behind one-distribution-per-month — the difference is that post meta has no unique index to lean on, so the condition goes in the WHERE clause. A ledger row already on the request refuses a second approval regardless of state, and a failed ledger write hands the claim back rather than leaving an approved request with no payment behind it. `bhela_bm_payreq_reject()` takes the same claim, or an approve and a reject racing could both win. `investor-test.php` §22 reproduces the race and was verified to fail against the old code: **the investor was paid 5,150 twice.**
51. **A sentinel date range is a silent filter wearing the costume of no filter.** Trip P&L resolved a blank filter to `2000-01-01 … +2 years`, which reads as "everything" and is not — a sheet dated earlier, or a trip booked further out, simply vanished. That is §13.24's failure in a new place, and the fix is the same shape: the bound comes from the data. `bhela_bm_trip_date_bound()` reads the earliest and latest trip date on any sheet, so a blank filter cannot exclude anything that exists.
52. **`bhela_bm_trip_report()` is for one trip, never for a list.** It re-queries every sheet in the trip's own month to work out the distribution share, so calling it once per row made the P&L list O(n²) — over a default filter that means every trip on record. `bhela_bm_trip_rows()` reads the five columns the list actually draws; `roundtrip-test.php` §11 pins the two readings against each other so they cannot drift.
53. **A comment that promises behaviour is a claim the tests should check.** `seasons.php` documented that "the settings screen warns rather than refusing" on overlapping seasons. Nothing warned — the sentence described an intention. `bhela_bm_season_overlaps()` and the notice now exist, and `investor-test.php` §24 asserts both the detection and that the earliest-starting season is the one that wins.

54. **`bhela_fund` was in no lock's post-type list for two releases.** `bhela_bm_dist_block_meta()` guarded `_bhela_dist_`, `_bhela_led_` **and** `_bhela_fnd_` keys — but only when `bhela_bm_dist_locked()` said so, and that function listed only `bhela_dist` and `bhela_inv_ledger`. So the `_bhela_fnd_` branch was dead code, and `bhela_bm_dist_block_delete()` shares the same predicate, which left a reserve allocation freely rewritable and **hard-deletable** from WP-CLI. A wider hole than §13.49's, because there was no lock at all rather than a lock with a gap — and §13.35 is explicit that an allocation is the arithmetic of a committed month and must not be cancellable even by reversal. The intent was never in doubt: `bhela_bm_fund_add()` writes every key through `bhela_bm_dist_meta_write()`, which exists only to lift a guard that never fired. §13.37 explains why no harness saw it — `bhela_fund` is deliberately outside `bhela_test_isolate()`. The lesson generalises past the fix: **a lock has two lists, hooks and post types, and asserting the first proves nothing about the second.** `investor-test.php` §23b now drives add / update / trash / hard delete against a real fund row.
55. **Two more delete routes walk past a lock that only ever asks about one post.** `delete_post_meta_by_key()` fires `delete_post_metadata` with `$object_id` of **0** and `$delete_all` true — "remove this key from every post" — so a guard that resolves a lock from the id found nothing and allowed it: one call could strip `_bhela_cost_total` from every approved sheet. It was also non-deterministic, because on a screen where the global `$post` happened to be a locked record, `get_post_type( 0 )` fell back to it and refused. All three locks were registered for **three** arguments, so `$delete_all` was never visible to them; the filter has passed five since WP 3.1. Separately, `delete_metadata_by_mid()` addresses a meta row by its own id through a different filter entirely, reachable from the REST API, and has to be resolved back to a post before the same question can be asked. Both are closed in all three locks now. Worth knowing which hooks *cannot* help: `added_post_meta` / `updated_post_meta` are post-hoc actions and cannot refuse, and `wp_insert_post( meta_input )` routes through `update_post_meta()` so it was already covered.
56. **A test can pin the wrong guard and still go green on revert.** `investor-test.php` §22 was titled "one request pays once, even under a race" and did not test the race: it approved, forced the state back through the meta API, approved again, and was refused by the `ledger > 0` belt-and-braces check. In a genuine race **both** callers read `ledger = 0`, so both pass that check and only the conditional UPDATE stops the second — which nothing asserted. Reverting the fix looked like it proved the test, because the revert removed both guards at once. The interleaving is reproducible in one process through the object cache: prime the cache, write the winner's state with `$wpdb->update()` and **no** `wp_cache_delete()`, and the next call reads exactly what a concurrent request holds. Verified by reverting only the claim and keeping the ledger guard: the old assertions passed, the new one failed with a second ৳3,131 paid. Note `update_post_meta()` finishes by *deleting* its cache entry rather than refreshing it, so the priming read is required.
57. **A performance assertion needs a threshold that separates the two implementations, not one that sounds generous.** The first version of §11's query-count check was `$after < $before * 6`. Measured both ways on the same fixture: the cheap reader costs 2 queries for one sheet and 5 for five; the per-row version costs 4 and 11. `11 < 4 * 6` passed, so the assertion would have shipped green against the very code it was written to reject. The **marginal** cost per added row does separate them — 0.75 against 1.75 — and survives an unrelated constant appearing at either end.

58. **Capital value and profit received are two kinds of money and are never added together.** One is unrealised — what the shares would fetch if the business were sold at the approved valuation — and the other is cash already in somebody's hand. A single "total return" figure tells an investor they have received money that is still in the boat. So `bhela_bm_investor_roi()` keeps meaning exactly what it meant (`investment` is what was paid in, `roi` is cash received ÷ invested) and `bhela_bm_investor_holding()` is a separate reader; every surface shows them in separate blocks, and the portal says in words that the gain is not cash. This is §13.44's rule ("two editable places holding one number") applied to a pair of figures that must stay apart rather than converge.
59. **A share's value changes; the share COUNT does not.** 115 shares stay 115 while the business grows — the valuation moves and `bhela_bm_share_value()` follows. New shares exist only when new money arrives, priced from an approved valuation, which is what makes the dilution fair: a 10-share holder goes from 8.696% to 8.197% while their holding value does not move at all, because the business took in cash worth exactly what the new shares are worth. That sentence is the answer to "why did my percentage go down". Issuing at the historic ৳1,00,000 after the business has grown is refused outright, not warned about — it is the transfer of value this module exists to prevent.
60. **A valuation snapshots its share count but derives its per-share value.** The two look like the same kind of figure and are not: `_bhela_val_shares` is a historical fact (what the divisor was that day, so a later issue cannot rewrite what a past valuation said), while per-share is `total ÷ shares` computed on every read (§13.8 — a derived figure that gets cached is a figure that goes stale). Growth % is likewise derived, against the previous **approved** valuation, falling back to `inv_total_investment` — which until v2.38.0 was a setting no code read at all.
61. **`bhela_bm_investor_amount()` must be read BEFORE a share issue raises the count.** It falls back to `shares × inv_per_share` when no paid-in amount was recorded, so reading it afterwards prices the just-issued shares at the OLD price and adds them to the basis again — a new investor's cost basis came out ৳7,00,000 too high, and appreciation with it. Caught by `valuation-test.php` §7 asserting the basis equals what was actually paid.
62. **The five `inv_*` settings had no admin UI at all until v2.38.0.** They existed only as defaults in `bhela_bm_default_settings()`, the save handler never touched them, and the code comment beside them claimed they were "configurable because a second boat or a fresh round would otherwise mean editing PHP". They were not. `inv_total_investment` was read by nothing whatsoever. The settings block added with the valuation module exposes four of them and renders `inv_total_shares` **read-only**, because a share issue is its only sanctioned writer and a settings box that could disagree with the issue history would put the divisor under every percentage out of step with the record of why it changed.

63. **A valuation is PRE-money, and it goes out of date the moment shares are issued against it.** `bhela_bm_holding_totals()` first tried to reconcile the holdings against the valuation total and produced a "rounding remainder" of **minus ten lakh** — because the divisor had moved from 115 to 122 while the recorded total was still the pre-money ৳1.70 Cr. There is no arithmetic that fixes this, because the post-money figure is a fact nothing has recorded yet. So the reconciliation is attempted only while `_bhela_val_shares` still equals the configured total, and otherwise `stale` and `issued_since` say plainly that a new valuation is needed. Reconciling against a number that has moved is worse than not reconciling: it produces a figure that looks like an error in the books.
64. **Holdings are rounded per share, and the gap is named rather than allocated away.** ৳1.70 Cr over 115 shares is ৳1,47,826.09, so `shares × per_share` is ten taka short of the valuation. §13.30's largest-remainder split would close it exactly — and is deliberately NOT used here, because an investor checking `10 × ৳1,47,826` on a calculator must get the number on their statement. The dashboard instead names both parts of the difference: the value of unissued shares, and the rounding remainder. A figure somebody can reproduce beats a total that reconciles.
65. **A record locked from birth cannot clean up after itself.** `bhela_bm_share_issue_commit()` aborts when the share total moved underneath it, and its `wp_delete_post()` was refused by its own lock — leaving an orphan issue record that `bhela_bm_share_issue_drift()` then counted as a real round, reporting drift on a correct register. `bhela_bm_val_delete()` is the sanctioned path: it lifts the two delete filters for exactly one call. The delete guards deliberately do **not** consult `bhela_bm_val_writing()` — a lock a flag can lift is not much of a lock — which is why this is a function with one caller rather than a condition inside the guard.
66. **A figure counted in SQL is outside the harness's post-type isolation.** `bhela_bm_share_issue_drift()` counts and sums in SQL so a capped listing cannot understate it (the `bhela_bm_payreq_pending_total()` failure). The cost is that raw SQL never sees `posts_where`, so the harness reads every round the site has ever run — and `valuation-test.php` §8 asserted absolutes against it and broke the moment a previous run left a record behind. Deltas, per §13.38, and the same rule now has a second instance: **any figure a harness reads through raw SQL must be asserted as a delta.**


> **Deployment: the portal must be served over HTTPS.** The sign-in form posts a password, and `wp_signon()` marks the session cookie secure only when `is_ssl()` is true. Over plain HTTP an investor's credentials and their session travel in clear on the network, and no amount of code here can compensate for it. This is the one item on this list that is a hosting decision rather than a bug.

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

# Run the regression suite (sixteen harnesses)
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
