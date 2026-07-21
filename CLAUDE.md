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
**Local dev:** LocalWP (Local by Flywheel) — site name `bhela-house-boat`
**Local URL:** http://bhela-house-boat.local/

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
│   │   ├── trips.php                ← Trip calendar admin + shortcode + availability
│   │   ├── reviews.php              ← Reviews CPT + admin + shortcode
│   │   ├── admin.php                ← Admin UI: columns, meta boxes, settings page, dashboard widget
│   │   └── guide.php                ← Embedded admin guide
│   ├── assets/
│   │   ├── booking.css              ← Booking form styles (29KB)
│   │   └── booking.js               ← Booking form logic + stepper wizard (41KB)
│   └── templates/
│       └── invoice.php              ← Printable invoice template
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
├── .claude/                         ← Claude Code local settings (git-ignored)
│   └── settings.local.json          ← Permission grants
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
Site name:    bhela-house-boat
Local URL:    http://bhela-house-boat.local/
WP root:      c:\Users\jashe\Local Sites\bhela-house-boat\app\public\
wp-content:   c:\Users\jashe\Local Sites\bhela-house-boat\app\public\wp-content\  ← Git root
```

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
- Plugin styles in `assets/booking.css` (29KB)
- CSS custom properties used for theming tokens

### 6.4 Bangla / i18n

- Page titles, labels, and UI text are primarily in **Bengali (বাংলা)**
- Text domain: `bhela` (theme), `bhela-booking` (plugin)
- No `.pot`/`.po` files currently — strings are hardcoded in Bengali

---

## 7. Version Management

### Version File Locations — **all five move together**

| File | Line | What to update |
|---|---|---|
| `themes/bhela/style.css` | 7 | `Version: X.Y.Z` |
| `themes/bhela/README.md` | 1 | `# 🎨 BHELA WordPress Theme (vX.Y.Z)` |
| `themes/bhela/functions.php` | 12 | `define( 'BHELA_VERSION', 'X.Y.Z' );` — **theme asset cache-buster; forgetting this ships stale CSS/JS** |
| `plugins/bhela-booking/bhela-booking.php` | 5 | ` * Version: X.Y.Z` |
| `plugins/bhela-booking/bhela-booking.php` | 16 | `define( 'BHELA_BM_VERSION', 'X.Y.Z' );` |

### Versioning Rules

- **Theme and Plugin share ONE version number.** A release bumps all five fields above to the same `X.Y.Z`, even if only one component changed. (History: they used to be versioned independently — that caused `BHELA_VERSION` to lag `style.css` and serve stale assets. Never again.)
- **Major** (X.0.0): breaking changes, full redesign
- **Minor** (X.Y.0): new features, templates, shortcodes
- **Patch** (X.Y.Z): bug fixes, style tweaks, copy changes

> ⚠️ **CRITICAL:** All five version fields MUST always match. The two theme constants (`BHELA_VERSION` in `functions.php`, `BHELA_BM_VERSION` in the plugin) are asset cache-busters — a mismatch is invisible in the header but breaks browser caching.

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
| `bhela_bm_calc_multi($cabins, $date)` | `bhela-booking.php` | Authoritative multi-cabin pricing |
| `bhela_bm_process_submission()` | `includes/frontend.php` | Processes new booking AJAX submit |
| `bhela_bm_trip_availability($date)` | `includes/trips.php` | Returns `total/booked/available/status` |
| `bhela_bm_send_sms($number, $msg)` | `includes/sms.php` | Send via configured gateway |
| `bhela_bm_render_sms($tpl, $id)` | `includes/sms.php` | Fill `{placeholders}` from booking |
| `bhela_bm_save_booking()` | `includes/admin.php` | Save booking meta + trigger notifications |

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

### Local Testing

- **Site check:** Visit http://bhela-house-boat.local/
- **Booking test:** Submit a test booking → verify AJAX response + booking in admin
- **Invoice test:** Open invoice link → verify rendering + print layout
- **Email test:** Use "Send Test Email" button in Bookings → Settings
- **SMS test:** Use "Send Test SMS" button (requires gateway config)
- **JS syntax:** `node --check assets/booking.js`

### Pre-Release Checks

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

1. **LocalWP emails:** Local sites don't send real mail. Use WP Mail SMTP or FluentSMTP plugin in production.
2. **Compress-Archive backslash bug:** Never use PowerShell's `Compress-Archive` for release ZIPs (see §8).
3. **Elementor override:** If Elementor is used on a page, the theme's coded sections for that page disappear entirely — by design.
4. **Homepage editor content:** Adding any Gutenberg blocks to the front page REPLACES the coded homepage design. Leave it empty to keep the designed homepage.
5. **Plugin-first activation:** Always activate the booking plugin BEFORE the theme, or auto-page creation may fail.
6. **settings.local.json is git-ignored:** The `.claude/` directory is excluded from git. Claude Code permissions need to be re-granted on each new machine.
7. **Bengali text in source:** Many strings are hardcoded in Bengali — no translation files exist.

---

## 14. AI Assistant Instructions

When working on this project as an AI assistant:

### DO:
- **Read this file first** before making any changes
- **Use the graphify knowledge graph** (`graphify-out/`) for architecture questions
- **Follow WordPress coding standards** — proper escaping, nonce verification, capability checks
- **Keep pricing logic in sync** between `bhela-booking.php` (PHP) and `booking.js` (JS)
- **Prefix all functions** with `bhela_` (theme) or `bhela_bm_` (plugin)
- **Test changes locally** at http://bhela-house-boat.local/
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

```powershell
# Check git status
git -C "c:\Users\jashe\Local Sites\bhela-house-boat\app\public\wp-content" status

# View recent commits
git -C "c:\Users\jashe\Local Sites\bhela-house-boat\app\public\wp-content" log --oneline -10

# Pull latest from GitHub
git -C "c:\Users\jashe\Local Sites\bhela-house-boat\app\public\wp-content" pull origin main

# Push to GitHub
git -C "c:\Users\jashe\Local Sites\bhela-house-boat\app\public\wp-content" push origin main

# Validate JS syntax
node --check "c:\Users\jashe\Local Sites\bhela-house-boat\app\public\wp-content\plugins\bhela-booking\assets\booking.js"

# Update knowledge graph
graphify update .

# Check site is running
curl -s -o /dev/null -w "site: %{http_code}\n" --max-time 12 "http://bhela-house-boat.local/"
```

---

*BHELA – The Haor Exclusive · "ভেলার আকর্ষণ ভেলা নয়, হাওর!" · Built by 3s-Soft*
