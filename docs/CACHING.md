# Caching BHELA safely

You can run a full-page cache and a CDN. Two things must be excluded, and one
problem in the plugin had to be fixed first (v2.25.1) — without that fix,
caching silently breaks the booking form no matter how you configure it.

## What was breaking

`wp_localize_script()` printed a WordPress nonce into the page HTML, and a
nonce is only valid for 12–24 hours. Uncached, that is invisible: every visitor
gets HTML rendered a second ago. Behind a cache, visitors are served HTML built
hours or days earlier, carrying a nonce that expired before they arrived.
`check_ajax_referer()` then rejects every call — availability, submit, tracker,
OTP — and `admin-ajax.php` answers with a bare `-1`.

From the guest's side the form simply stops working, with no error that names
the cause. From yours it looks like "caching breaks real-time data".

The page no longer carries a nonce. The browser fetches a fresh one on load and
retries once if a call is still refused, so a page cached for a week works.

## What to exclude from the cache

**1. `/wp-admin/admin-ajax.php`** — never cache it. Every cache plugin excludes
it by default; confirm yours has not been changed.

**2. The invoice URL: `?bhela_invoice=`** — this is the one that matters most.
An invoice carries a guest's name, phone, email and payment details. The plugin
sends `Cache-Control: no-store, private`, which is enough for browsers and
CDNs, but a full-page cache plugin runs *before* PHP and never sees those
headers. LiteSpeed Cache in particular can be configured to ignore query
strings, and would then serve one guest's invoice to the next visitor.

Add `bhela_invoice` to the cache plugin's URL/query-string exclusion list.

LiteSpeed Cache → Cache → Excludes → **Do Not Cache Query Strings**: `bhela_invoice`
WP Rocket → Advanced Rules → **Never Cache URLs**: `(.*)bhela_invoice(.*)`

**3. Do not cache logged-in users.** Default in every plugin worth using.

## What is safe to cache

Everything else, aggressively — the homepage, cabins, schedule, food, gallery,
FAQ, guide, policy and blog. They are static between edits.

The booking page and the trip calendar are safe **as pages**. Their live parts
come from `admin-ajax.php` after load, so the cached HTML holds no figure that
can go stale in a way that matters to a booking. The server re-checks
availability on submit and refuses an over-capacity booking regardless of what
the visitor's page showed.

## One caveat worth knowing

The public trip calendar renders "X/6 available" server-side. On a cached page
that number is as old as the cache. It is cosmetic — the booking itself is
validated live on submit, so nobody can book a cabin that is gone — but if you
cache for a long time, a sold-out trip can look open until the cache expires.

Set a shorter TTL on that page, or exclude it, if the appearance bothers you.
A one-hour TTL is a reasonable compromise.

## CDN

Safe once the two exclusions above are set. Add one thing:

If the CDN or a reverse proxy sits in front, tell the plugin so its rate limits
see the real visitor rather than the edge node. In `wp-config.php`:

```php
define( 'BHELA_TRUSTED_PROXIES', array( '203.0.113.10', '198.51.100.0/24' ) );
```

Without it, every visitor looks like the same IP: the first ten bookings
exhaust the hourly submit limit and the form closes for everyone. With an
address listed, the plugin reads `X-Forwarded-For` — and only from a request
that genuinely came from that address, so the header cannot be forged.

This also fixes the opposite problem on mobile. Bangladeshi carriers use CGNAT,
so thousands of subscribers share one address; the five-OTP daily cap was
locking out whole carriers. That limit is per phone number as well, which is
the one that actually matters.

## Checking it works

After enabling the cache, in a private window:

1. Load the booking page, wait for the cache to serve it (reload once).
2. Pick a date — availability must appear.
3. `curl -s -X POST -d "action=bhela_bm_nonce" https://YOUR-SITE/wp-admin/admin-ajax.php`
   must return JSON with a nonce, not cached HTML.
4. Open an invoice link, then open it in a different browser with no session —
   it must still show that booking, and the response must carry
   `Cache-Control: no-store`.
5. Leave the booking page open overnight and submit the next morning. It should
   work; that is the retry path.
