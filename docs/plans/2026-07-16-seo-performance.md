# SEO + Page Speed Overhaul — BHELA

> Mirror to `wp-content/docs/plans/2026-07-16-seo-performance.md` on implementation (project rule). Also finish the interrupted task: save the owner-manual artifact as standalone `docs/BHELA-Owner-Manual.html`.

## Context — audit findings (measured)

**Speed:** theme ships **18MB of images**. Homepage alone loads the 1.1MB hero + five ~900KB cabin JPEGs + seven 0.8–1.9MB spot JPEGs ≈ **12–14MB page weight** — the single dominant cause of slow LCP on mobile/3G. Everything else is already lean: one CSS file, no jQuery, footer scripts, lazy-loading below the fold, hero `fetchpriority=high`. Missing: font preconnect, CLS insurance on image slots.

**SEO:** solid skeleton (title-tag, semantic h1s, alt text everywhere, FAQPage + Article + TouristAttraction JSON-LD, WP core sitemap, pretty permalinks, private booking CPT) but **no meta descriptions, no Open Graph/Twitter cards** (shared links show bare), **`lang="en-US"` on Bangla content** (wrong language signal), no canonical on archives, schema lacks geo/priceRange, robots has no Sitemap line.

## Changes

### 1. Image optimization — the big win (`~18MB → ~2.5MB`)

One-time GD script (CLI, like previous verification scripts) that, for every JPEG/PNG under `themes/bhela/assets/images/`:
- Resize in place to max **1920px** wide (hero/) and **1200px** (cabins/, spots/, food/, boat/, info/), preserving aspect.
- Re-encode JPEG quality **82**, progressive, strip EXIF.
- Print before/after bytes per file + totals. Originals recoverable via git (assets are committed).
No markup changes needed — same filenames. Seeded blog posts already use WP media (auto-srcset) — untouched.

### 2. Performance polish — theme `functions.php` / `style.css`

- **Preconnect**: `wp_resource_hints` filter → `fonts.googleapis.com` + `fonts.gstatic.com` (crossorigin).
- **CLS insurance** (style.css): `aspect-ratio` on `.cabin-card__media img` (4/3), `.spot img` (cover slots), `.split__media img` already has aspect-ratio — verify; `.post-card > a img` (4/3).
- Note-only (server/production, documented in plan doc §5): page-cache plugin, OPcache, Brotli/GZIP, image CDN optional.

### 3. SEO module — new `themes/bhela/inc/seo.php` (required from functions.php)

- **Meta description** (`wp_head`, priority 1): front page → fixed Bangla+EN pitch; known page slugs (cabins/schedule/food/gallery/faq/book-now/policies/blog) → map reusing each page's hero lead text; single post → `get_the_excerpt` trimmed ~155 chars; category/tag → description or "এই বিষয়ে Nটি লেখা"; noindex nothing.
- **Open Graph + Twitter**: `og:site_name, og:type (website|article), og:title` (via `wp_get_document_title`), `og:description` (same source as meta), `og:url` (permalink/current), `og:image` (post featured image → else `bhela_img('hero')`), `og:locale bn_BD`, `twitter:card summary_large_image`.
- **Language**: `language_attributes` filter → `lang="bn-BD"` (site content is Bangla).
- **Canonical on archives**: output `rel=canonical` for blog home + term archives (paged-aware) — WP only covers singular.
- **robots.txt**: `robots_txt` filter appends `Sitemap: {home}/wp-sitemap.xml`.
- Skip if an SEO plugin is later active: bail when `defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION')`.

### 4. Schema upgrade — `bhela_schema()` in functions.php

`@type` → `["TouristAttraction","LodgingBusiness"]`, add: `image` (hero URL), `priceRange` (from live min/max cabin rates via `bhela_bm_get_rates()`, fallback "৳6,400–৳13,000"), `geo` (Tahirpur ≈ `25.09, 91.10`), `telephone`, `sameAs` (Facebook from `bhela_contact('facebook')`). FAQ + Article schema stay as-is.

### 5. Docs

- Finish `docs/BHELA-Owner-Manual.html` (wrap the artifact fragment with doctype/head/body — use PowerShell here-string or PHP, not the python heredoc that hung).
- Mirror this plan to `docs/plans/2026-07-16-seo-performance.md`, including a **production go-live checklist**: install a page-cache plugin, enable server Brotli/GZIP + OPcache, HTTPS, FluentSMTP domain alignment (SPF/DKIM), Google Search Console — submit `/wp-sitemap.xml`, Google Business Profile for local SEO.

### 6. Version

Theme `2.6.7 → 2.7.0` (feature). `graphify update .`

## Reuse

- `bhela_img()`, `bhela_contact()`, `bhela_bm_get_rates()`; existing schema pattern (`bhela_schema()` functions.php); page hero lead texts already written per template — the description map copies them.
- GD already used by the seed-thumbnail code; same Local PHP CLI + mysqli port pattern for scripts.

## Verification

1. **Images**: script prints totals; assert `du -sh assets/images` ≤ ~3MB; spot-check /cabins/ and homepage in browser — visually unchanged, no broken images.
2. **Homepage HTML**: contains `name="description"`, `og:title/og:image/og:url`, `twitter:card`, two `preconnect` links, `<html lang="bn-BD"`.
3. **Post page**: `og:type article`, excerpt-based description; **category page**: canonical present.
4. `curl /robots.txt` shows the Sitemap line; `/wp-sitemap.xml` → 200.
5. Page-weight proof: sum of image bytes referenced by homepage before vs after (curl each img URL, total).
6. Regression: all 9 pages 200 + markers; booking flow unaffected (no plugin changes); PHP lint; graphify update.

## Production go-live checklist (server-side — cannot be done in the theme)

- [ ] **Page cache plugin** — install WP Super Cache / LiteSpeed Cache / W3TC on the live host. Biggest server-side speed win.
  - ⚠️ **Exclude the Book Now page (`/book-now/`) from page cache.** It carries a WordPress nonce; a cached copy serves a stale nonce to every visitor, and once it expires (~12–24h) the availability check *and booking submission* start failing (`check_ajax_referer` → `-1`). Add `/book-now/` to the cache plugin's "never cache these pages" list. (`admin-ajax.php` is already never cached by these plugins.) The booking JS now degrades gracefully if an availability check can't be verified, but submission still needs a live nonce — so the exclusion is required, not optional.
- [ ] **PHP OPcache** enabled on the host (most managed WP hosts have it on — verify).
- [ ] **Brotli or GZIP** compression enabled at the web server (check response header `content-encoding`).
- [ ] **HTTPS** with valid certificate; force-redirect http → https.
- [ ] **FluentSMTP domain alignment** — SPF + DKIM records for the sending domain so booking emails land in inbox, not spam.
- [ ] **Google Search Console** — verify property, submit `https://<domain>/wp-sitemap.xml`.
- [ ] **Google Business Profile** — create/claim listing for local "টাঙ্গুয়ার হাওর হাউসবোট" searches; link the website.
- [ ] Optional: image CDN (Cloudflare free tier is enough) for global edge caching.

## Result (implemented 2026-07-17)

- Theme images: **17.2MB → 4.3MB (−74%)** — resized in place (hero 1920px, rest 1200px), progressive JPEG q82.
- New `inc/seo.php`: meta descriptions (per-page Bangla map), OG + Twitter cards, `lang="bn-BD"`, archive canonicals, robots.txt Sitemap line, font preconnect. Bails if Yoast/Rank Math active.
- `bhela_schema()` upgraded: `["TouristAttraction","LodgingBusiness"]`, live `priceRange` from cabin rates, `geo`, `image`, `sameAs`.
- CLS: `.post-card > a img` aspect-ratio 4/3 (cabin/spot slots already fixed-size).
- Theme v2.7.0.
